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

final class TicketController
{
    private Auth $auth;
    private TicketRepository $tickets;
    private CommandeRepository $commandes;
    private UserRepository $users;

    public function __construct()
    {
        $this->auth = new Auth();
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
            http_response_code(404);
            echo 'Ticket introuvable.';

            return;
        }

        View::render('tickets/show', [
            'ticket' => $ticket,
            'historique' => $this->tickets->historique($id),
            'techniciens' => $this->users->findTechniciens(),
            'transitions' => $ticket->statut->transitionsAutorisees(),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('tickets/create', [
            'commandes' => $this->commandes->findAllAvecClient(),
            'types' => TicketType::cases(),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $request): void
    {
        $commandeId = $request->intInput('commande_id');
        $type = TicketType::tryFrom($request->input('type'));
        $description = $request->input('description');

        if ($commandeId === null || $type === null) {
            Session::flash('error', 'Commande et type sont obligatoires.');
            header('Location: /tickets/create');

            return;
        }

        $code = 'TCK-' . str_pad((string) (random_int(1, 9999)), 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);

        $id = $this->tickets->create($code, $commandeId, null, $type, $description !== '' ? $description : null);

        header('Location: /tickets/' . $id);
    }

    public function changerStatut(Request $request): void
    {
        $id = (int) $request->param('id');
        $ticket = $this->tickets->findById($id);
        $nouveauStatut = TicketStatut::tryFrom($request->input('statut'));
        $user = $this->auth->user();

        if ($ticket === null || $nouveauStatut === null || $user === null) {
            Session::flash('error', 'Requete invalide.');
            header('Location: /tickets/' . $id);

            return;
        }

        if (!in_array($nouveauStatut, $ticket->statut->transitionsAutorisees(), true)) {
            Session::flash('error', "Transition de statut non autorisee ({$ticket->statut->label()} -> {$nouveauStatut->label()}).");
            header('Location: /tickets/' . $id);

            return;
        }

        $this->tickets->changerStatut($id, $ticket->statut, $nouveauStatut, $user->id);
        header('Location: /tickets/' . $id);
    }

    public function assigner(Request $request): void
    {
        $id = (int) $request->param('id');
        $technicienId = $request->intInput('technicien_id');

        if ($technicienId !== null) {
            $this->tickets->assignerTechnicien($id, $technicienId);
        }

        header('Location: /tickets/' . $id);
    }
}
