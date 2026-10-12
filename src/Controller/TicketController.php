<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enum\TicketStatut;
use App\Enum\TicketType;
use App\Repository\CommandeRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Validation\TicketValidator;

final class TicketController
{
    private Auth $auth;
    private TicketRepository $tickets;
    private CommandeRepository $commandes;
    private UserRepository $users;

    public function __construct(?Auth $auth = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->tickets = new TicketRepository();
        $this->commandes = new CommandeRepository();
        $this->users = new UserRepository();
    }

    public function index(Request $request): void
    {
        $statutFiltre = $_GET['statut'] ?? null;
        $statut = is_string($statutFiltre) && $statutFiltre !== '' ? TicketStatut::tryFrom($statutFiltre) : null;

        View::render('tickets/index', [
            'tickets' => $this->tickets->findAll($statut),
            'statuts' => TicketStatut::cases(),
            'statutActif' => $statut,
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
            'types' => TicketType::cases(),
            'erreurs' => Session::pullFlashDonnees('erreurs'),
            'anciennes' => Session::pullFlashDonnees('anciennes'),
            'longueurMaxDescription' => TicketValidator::LONGUEUR_MAX_DESCRIPTION,
        ]);
    }

    public function store(Request $request): void
    {
        $donnees = TicketValidator::normaliser($_POST);
        $erreurs = TicketValidator::valider($donnees, $this->commandes->existe(...));

        if ($erreurs !== []) {
            Session::flashDonnees('erreurs', $erreurs);
            Session::flashDonnees('anciennes', $donnees);
            header('Location: /tickets/create');

            return;
        }

        $id = $this->tickets->create(
            (int) $donnees['commande_id'],
            TicketType::from($donnees['type']),
            $donnees['description'] !== '' ? $donnees['description'] : null,
        );

        Session::flash('success', 'Ticket ' . TicketRepository::code($id) . ' cree.');
        header('Location: /tickets/' . $id);
    }

    public function changerStatut(Request $request): void
    {
        $id = (int) $request->param('id');
        $ticket = $this->tickets->findById($id);

        if ($ticket === null) {
            $this->ticketIntrouvable();

            return;
        }

        $nouveauStatut = TicketStatut::tryFrom($request->input('statut'));
        $user = $this->auth->user();

        if ($nouveauStatut === null || $user === null) {
            Session::flash('error', 'Statut inconnu.');
            header('Location: /tickets/' . $id);

            return;
        }

        if (!in_array($nouveauStatut, $ticket->statut->transitionsAutorisees(), true)) {
            Session::flash('error', "Transition de statut non autorisee ({$ticket->statut->label()} -> {$nouveauStatut->label()}).");
            header('Location: /tickets/' . $id);

            return;
        }

        $this->tickets->changerStatut($id, $ticket->statut, $nouveauStatut, $user->id);
        Session::flash('success', "Statut passe a \"{$nouveauStatut->label()}\".");
        header('Location: /tickets/' . $id);
    }

    public function assigner(Request $request): void
    {
        $id = (int) $request->param('id');
        $ticket = $this->tickets->findById($id);

        if ($ticket === null) {
            $this->ticketIntrouvable();

            return;
        }

        $technicienId = $request->intInput('technicien_id');
        // Verifie en base que c'est un technicien : sans ca, un id inexistant
        // faisait planter la page (cle etrangere) et l'id d'un admin etait
        // accepte.
        $technicien = $technicienId !== null ? $this->users->findTechnicien($technicienId) : null;

        $erreur = match (true) {
            $ticket->statut === TicketStatut::Termine => 'Un ticket termine ne peut plus etre reassigne.',
            $technicienId === null => 'Choisissez un technicien.',
            $technicien === null => 'Technicien introuvable (seuls les comptes technicien peuvent etre assignes).',
            default => null,
        };

        if ($erreur !== null) {
            Session::flash('error', $erreur);
            header('Location: /tickets/' . $id);

            return;
        }

        $this->tickets->assignerTechnicien($id, $technicien->id);
        Session::flash('success', "Ticket assigne a {$technicien->nomComplet()}.");
        header('Location: /tickets/' . $id);
    }

    private function ticketIntrouvable(): void
    {
        http_response_code(404);
        echo 'Ticket introuvable.';
    }
}
