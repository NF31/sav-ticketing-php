<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\ApresReponse;
use App\Core\Mail\Mailer;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\TicketStatut;
use App\Repository\NotificationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use PDOException;

/**
 * Decide qui prevenir quand un ticket change, appele par TicketService
 * apres chaque modification reussie. Regle commune : on ne notifie jamais
 * l'auteur de l'action (il sait deja ce qu'il vient de faire).
 *
 * - nouveau ticket        -> les admins, qui l'assignent
 * - assignation           -> le technicien assigne (+ email : il n'est pas
 *                            forcement connecte)
 * - reassignation         -> l'ancien technicien, qui n'a plus a s'en occuper
 * - changement de statut  -> le technicien assigne
 */
final class Notifieur
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
        private readonly string $urlApplication,
    ) {
    }

    public function ticketCree(int $ticketId, User $auteur): void
    {
        $code = TicketRepository::code($ticketId);

        foreach ($this->users->findAdmins() as $admin) {
            if ($admin->id !== $auteur->id) {
                $this->notifier($admin->id, $ticketId, NotificationType::NouveauTicket, "Nouveau ticket {$code} a assigner (cree par {$auteur->nomComplet()}).");
            }
        }
    }

    /** @param Ticket $avant le ticket tel qu'il etait avant l'assignation */
    public function ticketAssigne(Ticket $avant, User $technicien, User $auteur): void
    {
        // Reassigner le meme technicien ne change rien pour personne.
        if ($avant->technicienId === $technicien->id) {
            return;
        }

        if ($technicien->id !== $auteur->id) {
            $this->notifier($technicien->id, $avant->id, NotificationType::Assignation, "Le ticket {$avant->code} vous a ete assigne par {$auteur->nomComplet()}.");
            $this->envoyerEmailAssignation($avant, $technicien, $auteur);
        }

        if ($avant->technicienId !== null && $avant->technicienId !== $auteur->id) {
            $this->notifier($avant->technicienId, $avant->id, NotificationType::Desassignation, "Le ticket {$avant->code} a ete reassigne a {$technicien->nomComplet()} par {$auteur->nomComplet()}.");
        }
    }

    /** @param Ticket $avant le ticket tel qu'il etait avant le changement */
    public function statutChange(Ticket $avant, TicketStatut $nouveauStatut, User $auteur): void
    {
        if ($avant->technicienId === null || $avant->technicienId === $auteur->id) {
            return;
        }

        $this->notifier(
            $avant->technicienId,
            $avant->id,
            NotificationType::Statut,
            "Le ticket {$avant->code} est passe de \"{$avant->statut->label()}\" a \"{$nouveauStatut->label()}\" (par {$auteur->nomComplet()}).",
        );
    }

    /**
     * Une notification qui echoue ne doit pas faire echouer l'action deja
     * enregistree (le ticket est assigne, l'utilisateur doit le voir) :
     * l'erreur est journalisee, pas remontee.
     */
    private function notifier(int $userId, int $ticketId, NotificationType $type, string $message): void
    {
        try {
            $this->notifications->creer($userId, $ticketId, $type, $message);
        } catch (PDOException $e) {
            error_log("[Notifieur] notification {$type->value} non enregistree (ticket {$ticketId}) : {$e->getMessage()}");
        }
    }

    /** Envoye apres la reponse (ApresReponse) : l'appel a l'API mail ne ralentit pas la page. */
    private function envoyerEmailAssignation(Ticket $ticket, User $technicien, User $auteur): void
    {
        $lien = rtrim($this->urlApplication, '/') . '/tickets/' . $ticket->id;
        $client = $ticket->clientNomComplet !== null ? ", client {$ticket->clientNomComplet}" : '';

        ApresReponse::ajouter(fn (): bool => $this->mailer->send(
            $technicien->email,
            "Ticket {$ticket->code} assigne",
            "Bonjour {$technicien->prenom},\n\n"
            . "{$auteur->nomComplet()} vous a assigne le ticket {$ticket->code} ({$ticket->type->label()}{$client}).\n\n"
            . "Voir le ticket :\n{$lien}\n",
        ));
    }
}
