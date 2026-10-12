<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enum\Poste;
use App\Repository\UserRepository;
use App\Validation\MotDePasse;
use App\Validation\TechnicienValidator;

final class AdminController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function techniciens(Request $request): void
    {
        View::render('admin/techniciens', [
            'utilisateurs' => $this->users->findAll(),
            'postes' => Poste::cases(),
            'erreurs' => Session::pullFlashDonnees('erreurs'),
            'anciennes' => Session::pullFlashDonnees('anciennes'),
            'success' => Session::pullFlash('success'),
            'longueurMaxNom' => TechnicienValidator::LONGUEUR_MAX_NOM,
            'longueurMaxEmail' => TechnicienValidator::LONGUEUR_MAX_EMAIL,
            'longueurMinMotDePasse' => MotDePasse::LONGUEUR_MIN,
        ]);
    }

    public function creerTechnicien(Request $request): void
    {
        $donnees = TechnicienValidator::normaliser($request->donnees());
        $erreurs = TechnicienValidator::valider($donnees, $this->users->emailExiste(...));

        if ($erreurs === []) {
            $id = $this->users->creerTechnicien(
                $donnees['nom'],
                $donnees['prenom'],
                $donnees['email'],
                $donnees['password'],
                Poste::from($donnees['poste']),
            );

            if ($id !== null) {
                Session::flash('success', "Technicien {$donnees['prenom']} {$donnees['nom']} cree.");
                header('Location: /admin/techniciens');

                return;
            }

            // Email pris entre la verification et l'INSERT (creation simultanee).
            $erreurs['email'] = 'Un compte existe deja avec cet email.';
        }

        // Valeurs saisies renvoyees au formulaire, sauf les mots de passe :
        // ils ne doivent ni rester en session ni revenir dans le HTML.
        unset($donnees['password'], $donnees['password_confirmation']);
        Session::flashDonnees('erreurs', $erreurs);
        Session::flashDonnees('anciennes', $donnees);
        header('Location: /admin/techniciens');
    }
}
