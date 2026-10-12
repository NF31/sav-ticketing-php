<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\TicketType;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Repository\TicketRepository;
use DateTimeImmutable;

/**
 * Transforme une demande de retour kbeauty-shop (message deja valide par
 * ContratRetourSoumis) en ticket SAV de type "retour" : client et commande
 * retrouves ou crees, ticket cree, admins notifies pour l'assigner.
 *
 * Idempotent : RabbitMQ livre "au moins une fois", un meme message peut
 * arriver deux fois. Le ticket est rattache a returnRequestId (UNIQUE en
 * base) : le second passage ne cree rien.
 */
final class ImportRetourKbeauty
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly CommandeRepository $commandes,
        private readonly TicketRepository $tickets,
        private readonly Notifieur $notifieur,
    ) {
    }

    /** @return int|null id du ticket cree, null si ce retour avait deja ete importe */
    public function importer(object $retour): ?int
    {
        if ($this->tickets->findIdParRetourKbeauty($retour->returnRequestId) !== null) {
            return null;
        }

        $commandeId = $this->commandes->trouverOuCreerParReference(
            $retour->order->number,
            $this->client($retour->customer),
            new DateTimeImmutable($retour->order->placedAt)->format('Y-m-d'),
        );

        try {
            $ticketId = $this->tickets->create($commandeId, TicketType::Retour, self::description($retour), $retour->returnRequestId);
        } catch (\PDOException $e) {
            // Meme retour importe entre-temps par un autre consommateur :
            // la contrainte UNIQUE sur retour_kbeauty_id a tranche.
            if (($e->errorInfo[1] ?? null) === 1062) {
                return null;
            }

            throw $e;
        }

        $this->notifieur->retourRecu($ticketId, $retour->order->number);

        return $ticketId;
    }

    /** Client existant (meme email) reutilise tel quel, sinon cree. */
    private function client(object $client): int
    {
        $existant = $this->clients->findIdParEmail($client->email);
        if ($existant !== null) {
            return $existant;
        }

        [$prenom, $nom] = self::separerNom($client->name);

        return $this->clients->create($nom, $prenom, $client->email, null, null, null, null)
            ?? $this->clients->findIdParEmail($client->email)
            ?? throw new \LogicException("Client {$client->email} ni cree ni trouve.");
    }

    /**
     * La boutique a un seul champ "nom" ; le SAV separe prenom et nom.
     * "Jeanne Martin" -> ["Jeanne", "Martin"] ; "Cher" -> ["", "Cher"].
     *
     * @return array{string, string} [prenom, nom], tronques a la taille des colonnes
     */
    public static function separerNom(string $nomComplet): array
    {
        $morceaux = preg_split('/\s+/u', trim($nomComplet), 2) ?: [];
        [$prenom, $nom] = count($morceaux) === 2 ? $morceaux : ['', $morceaux[0] ?? ''];

        return [mb_substr($prenom, 0, 100), mb_substr($nom, 0, 100)];
    }

    /** Texte du ticket : motif du client puis articles retournes (affiche comme texte, jamais comme HTML). */
    public static function description(object $retour): string
    {
        $articles = array_map(
            static fn (object $article): string => sprintf(
                '- %d x %s%s (%s %s)',
                $article->quantity,
                $article->productName,
                $article->variantLabel !== null ? " - {$article->variantLabel}" : '',
                number_format($article->amountCents / 100, 2, ',', ' '),
                $retour->order->currency,
            ),
            $retour->items,
        );

        return "Motif du client :\n{$retour->reason}\n\n"
            . "Articles retournes :\n" . implode("\n", $articles) . "\n\n"
            . "Demande de retour n°{$retour->returnRequestId} de la boutique, commande {$retour->order->number}.";
    }
}
