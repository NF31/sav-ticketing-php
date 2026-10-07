<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repository\ClientRepository;
use App\Validation\ClientValidator;

final class ClientController
{
    private ClientRepository $clients;

    public function __construct()
    {
        $this->clients = new ClientRepository();
    }

    public function index(Request $request): void
    {
        View::render('clients/index', [
            'clients' => $this->clients->findAll(),
            'success' => Session::pullFlash('success'),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('clients/create', [
            'erreurs' => Session::pullFlashDonnees('erreurs'),
            'anciennes' => Session::pullFlashDonnees('anciennes'),
            'longueurMaxNom' => ClientValidator::LONGUEUR_MAX_NOM,
            'longueurMaxEmail' => ClientValidator::LONGUEUR_MAX_EMAIL,
            'longueurMaxRue' => ClientValidator::LONGUEUR_MAX_RUE,
            'longueurMaxVille' => ClientValidator::LONGUEUR_MAX_VILLE,
        ]);
    }

    public function store(Request $request): void
    {
        $donnees = ClientValidator::normaliser($_POST);
        $erreurs = ClientValidator::valider($donnees, $this->clients->emailExiste(...));

        if ($erreurs === []) {
            $id = $this->clients->create(
                $donnees['nom'],
                $donnees['prenom'],
                $donnees['email'],
                $donnees['telephone'] ?: null,
                $donnees['adresse_rue'] ?: null,
                $donnees['adresse_ville'] ?: null,
                $donnees['adresse_code_postal'] ?: null,
            );

            if ($id !== null) {
                Session::flash('success', "Client {$donnees['prenom']} {$donnees['nom']} cree.");
                header('Location: /clients');

                return;
            }

            // Email pris entre la verification et l'INSERT (creation simultanee).
            $erreurs['email'] = 'Un client existe deja avec cet email.';
        }

        Session::flashDonnees('erreurs', $erreurs);
        Session::flashDonnees('anciennes', $donnees);
        header('Location: /clients/create');
    }
}
