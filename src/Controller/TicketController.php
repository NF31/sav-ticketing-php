<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Pagination;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Entity\User;
use App\Enum\TicketStatut;
use App\Enum\TicketType;
use App\Repository\CommandeRepository;
use App\Repository\NotificationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\Exception\RegleMetierViolee;
use App\Service\Exception\SaisieInvalide;
use App\Service\Exception\TicketIntrouvable;
use App\Service\TicketService;
use App\Validation\TicketValidator;

/**
 * Couche HTTP : lit la requete, appelle TicketService pour toute
 * modification, puis choisit la reponse (redirection, message, 404).
 * Aucune regle metier ici.
 */
final class TicketController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly TicketService $service,
        private readonly TicketRepository $tickets,
        private readonly CommandeRepository $commandes,
        private readonly UserRepository $users,
        private readonly NotificationRepository $notifications,
    ) {
    }

    public function index(Request $request): void
    {
        $statutFiltre = $request->query('statut');
        $statut = $statutFiltre !== null && $statutFiltre !== '' ? TicketStatut::tryFrom($statutFiltre) : null;

        $pagination = Pagination::depuis($request->query('page'), $this->tickets->compter($statut));

        View::render('tickets/index', [
            'tickets' => $this->tickets->findPage($statut, $pagination->parPage, $pagination->offset()),
            'statuts' => TicketStatut::cases(),
            'statutActif' => $statut,
            'pagination' => $pagination,
        ]);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $ticket = $this->tickets->findById($id);

        if ($ticket === null) {
            $this->ticketIntrouvable();

            return;
        }

        // Ouvrir le ticket vaut lecture de ses notifications (badge a jour
        // des cette page).
        $this->notifications->marquerLuesPourTicket($this->utilisateur()->id, $id);

        View::render('tickets/show', [
            'ticket' => $ticket,
            'historique' => $this->tickets->historique($id),
            'techniciens' => $this->users->findTechniciens(),
            'transitions' => $ticket->statut->transitionsAutorisees(),
            'error' => Session::pullFlash('error'),
            'success' => Session::pullFlash('success'),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('tickets/create', [
            'commandes' => $this->commandes->findAllAvecClient(),
            'types' => TicketType::saisissables(),
            'erreurs' => Session::pullFlashDonnees('erreurs'),
            'anciennes' => Session::pullFlashDonnees('anciennes'),
            'longueurMaxDescription' => TicketValidator::LONGUEUR_MAX_DESCRIPTION,
        ]);
    }

    public function store(Request $request): void
    {
        try {
            $id = $this->service->creer($request->donnees(), $this->utilisateur());
        } catch (SaisieInvalide $e) {
            Session::flashDonnees('erreurs', $e->erreurs);
            Session::flashDonnees('anciennes', $e->donnees);
            header('Location: /tickets/create');

            return;
        }

        Session::flash('success', 'Ticket ' . TicketRepository::code($id) . ' cree.');
        header('Location: /tickets/' . $id);
    }

    public function changerStatut(Request $request): void
    {
        $id = (int) $request->param('id');
        $nouveauStatut = TicketStatut::tryFrom($request->input('statut'));

        if ($nouveauStatut === null) {
            $this->retourAvecErreur($id, 'Statut inconnu.');

            return;
        }

        try {
            $this->service->changerStatut($id, $nouveauStatut, $this->utilisateur());
        } catch (TicketIntrouvable) {
            $this->ticketIntrouvable();

            return;
        } catch (RegleMetierViolee $e) {
            $this->retourAvecErreur($id, $e->getMessage());

            return;
        }

        Session::flash('success', "Statut passe a \"{$nouveauStatut->label()}\".");
        header('Location: /tickets/' . $id);
    }

    public function assigner(Request $request): void
    {
        $id = (int) $request->param('id');

        try {
            $technicien = $this->service->assigner($id, $request->intInput('technicien_id'), $this->utilisateur());
        } catch (TicketIntrouvable) {
            $this->ticketIntrouvable();

            return;
        } catch (RegleMetierViolee $e) {
            $this->retourAvecErreur($id, $e->getMessage());

            return;
        }

        Session::flash('success', "Ticket assigne a {$technicien->nomComplet()}.");
        header('Location: /tickets/' . $id);
    }

    /** Routes protegees (auth: true) : le routeur a deja verifie la connexion. */
    private function utilisateur(): User
    {
        return $this->auth->user() ?? throw new \LogicException('Utilisateur connecte attendu sur une route protegee.');
    }

    private function retourAvecErreur(int $id, string $message): void
    {
        Session::flash('error', $message);
        header('Location: /tickets/' . $id);
    }

    private function ticketIntrouvable(): void
    {
        http_response_code(404);
        echo 'Ticket introuvable.';
    }
}
