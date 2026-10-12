<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatut;
use App\Enum\TicketType;
use App\Repository\CommandeRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Exception\RegleMetierViolee;
use App\Service\Exception\SaisieInvalide;
use App\Service\Exception\TicketIntrouvable;
use App\Validation\TicketValidator;

/**
 * Cas d'usage qui modifient un ticket : creation, changement de statut,
 * assignation. Les regles metier y sont appliquees, independamment du
 * HTTP : le controleur traduit la requete en appel, puis le resultat (ou
 * l'exception) en reponse. La meme regle s'appliquerait donc a une API ou
 * une commande CLI, sans etre recopiee.
 *
 * Les lectures simples (liste, detail) restent en acces direct aux
 * repositories : il n'y a aucune regle a y appliquer.
 */
final class TicketService
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly CommandeRepository $commandes,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @param array<string, mixed> $saisie corps du formulaire de creation
     * @return int id du ticket cree
     * @throws SaisieInvalide
     */
    public function creer(array $saisie): int
    {
        $donnees = TicketValidator::normaliser($saisie);
        $erreurs = TicketValidator::valider($donnees, $this->commandes->existe(...));

        if ($erreurs !== []) {
            throw new SaisieInvalide($erreurs, $donnees);
        }

        return $this->tickets->create(
            (int) $donnees['commande_id'],
            TicketType::from($donnees['type']),
            $donnees['description'] !== '' ? $donnees['description'] : null,
        );
    }

    /**
     * @throws TicketIntrouvable
     * @throws RegleMetierViolee
     */
    public function changerStatut(int $ticketId, TicketStatut $nouveauStatut, int $auteurId): void
    {
        $ticket = $this->trouver($ticketId);

        if (!$ticket->statut->peutPasserA($nouveauStatut)) {
            throw new RegleMetierViolee("Transition de statut non autorisee ({$ticket->statut->label()} -> {$nouveauStatut->label()}).");
        }

        // Le repository ne modifie le ticket que s'il a encore le statut lu
        // ci-dessus : si quelqu'un l'a change entre-temps, la transition
        // verifiee n'est plus la bonne.
        if (!$this->tickets->changerStatut($ticketId, $ticket->statut, $nouveauStatut, $auteurId)) {
            throw new RegleMetierViolee('Le ticket vient d\'etre modifie par quelqu\'un d\'autre. Verifiez son statut et reessayez.');
        }
    }

    /**
     * @return User le technicien assigne, pour le message de confirmation
     * @throws TicketIntrouvable
     * @throws RegleMetierViolee
     */
    public function assigner(int $ticketId, ?int $technicienId): User
    {
        $ticket = $this->trouver($ticketId);

        // Verifie en base que c'est un technicien : sans ca, un id inexistant
        // faisait planter la page (cle etrangere) et l'id d'un admin etait
        // accepte.
        $technicien = $technicienId !== null ? $this->users->findTechnicien($technicienId) : null;

        $erreur = match (true) {
            !$ticket->statut->estReassignable() => 'Un ticket termine ne peut plus etre reassigne.',
            $technicienId === null => 'Choisissez un technicien.',
            $technicien === null => 'Technicien introuvable (seuls les comptes technicien peuvent etre assignes).',
            default => null,
        };

        if ($erreur !== null) {
            throw new RegleMetierViolee($erreur);
        }

        // Refuse aussi si le ticket a ete termine entre la lecture et l'ecriture.
        if (!$this->tickets->assignerTechnicien($ticketId, $technicien->id)) {
            throw new RegleMetierViolee('Le ticket vient d\'etre termine : il ne peut plus etre reassigne.');
        }

        return $technicien;
    }

    /** @throws TicketIntrouvable */
    private function trouver(int $ticketId): Ticket
    {
        return $this->tickets->findById($ticketId) ?? throw new TicketIntrouvable($ticketId);
    }
}
