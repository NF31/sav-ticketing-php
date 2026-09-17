<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repository\ClientRepository;

final class ClientController
{
    private ClientRepository $clients;

    public function __construct()
    {
        $this->clients = new ClientRepository();
    }

    public function index(Request $request): void
    {
        View::render('clients/index', ['clients' => $this->clients->findAll()]);
    }

    public function create(Request $request): void
    {
        View::render('clients/create', ['error' => Session::pullFlash('error')]);
    }

    public function store(Request $request): void
    {
        $nom = $request->input('nom');
        $prenom = $request->input('prenom');
        $email = $request->input('email');

        if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Nom, prenom et email valide sont obligatoires.');
            header('Location: /clients/create');

            return;
        }

        $this->clients->create(
            $nom,
            $prenom,
            $email,
            $request->input('telephone') ?: null,
            $request->input('adresse_rue') ?: null,
            $request->input('adresse_ville') ?: null,
            $request->input('adresse_code_postal') ?: null,
        );

        header('Location: /clients');
    }
}
