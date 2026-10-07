<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Env;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;

final class PasswordResetController
{
    private const int LONGUEUR_MIN_MOT_DE_PASSE = 8;

    /**
     * Message identique que l'email existe ou non : sinon le formulaire
     * permettrait de tester quels emails ont un compte (enumeration).
     */
    private const string MESSAGE_DEMANDE_ENVOYEE = 'Si un compte existe pour cet email, un lien de reinitialisation vient d\'etre envoye. Il est valable '
        . PasswordResetRepository::DUREE_VALIDITE_MINUTES . ' minutes.';

    private UserRepository $users;
    private PasswordResetRepository $resets;
    private Mailer $mailer;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->resets = new PasswordResetRepository();
        $this->mailer = new Mailer();
    }

    public function showDemande(Request $request): void
    {
        View::render('auth/mot-de-passe-oublie', ['error' => Session::pullFlash('error')]);
    }

    public function envoyerLien(Request $request): void
    {
        $email = $request->input('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Adresse email invalide.');
            header('Location: /mot-de-passe-oublie');

            return;
        }

        $user = $this->users->findByEmail($email);

        if ($user !== null) {
            $jeton = $this->resets->creerJeton($user->id);

            // URL de base lue dans la config, jamais dans l'en-tete Host de la
            // requete : un attaquant pourrait sinon forger un Host pointant vers
            // son domaine et recevoir le jeton quand la victime clique le lien.
            $lien = rtrim(Env::get('APP_URL', 'http://localhost:8090'), '/') . '/mot-de-passe/reinitialiser/' . $jeton;

            $this->mailer->send(
                $user->email,
                'Reinitialisation de votre mot de passe',
                "Bonjour {$user->prenom},\n\n"
                . "Pour choisir un nouveau mot de passe, ouvrez ce lien :\n{$lien}\n\n"
                . 'Il est valable ' . PasswordResetRepository::DUREE_VALIDITE_MINUTES . " minutes et ne peut servir qu'une fois.\n"
                . "Si vous n'etes pas a l'origine de cette demande, ignorez cet email.",
            );
        }

        Session::flash('success', self::MESSAGE_DEMANDE_ENVOYEE);
        header('Location: /login');
    }

    public function showReinitialisation(Request $request): void
    {
        $jeton = $request->param('token');

        if ($this->resets->trouverUtilisateurPourJeton($jeton) === null) {
            Session::flash('error', 'Ce lien de reinitialisation est invalide ou a expire. Faites une nouvelle demande.');
            header('Location: /mot-de-passe-oublie');

            return;
        }

        View::render('auth/reinitialiser-mot-de-passe', [
            'jeton' => $jeton,
            'longueurMin' => self::LONGUEUR_MIN_MOT_DE_PASSE,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function reinitialiser(Request $request): void
    {
        $jeton = $request->param('token');
        // Pas de trim() : un espace en debut/fin de mot de passe est volontaire.
        $motDePasse = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        $erreur = match (true) {
            strlen($motDePasse) < self::LONGUEUR_MIN_MOT_DE_PASSE => 'Le mot de passe doit contenir au moins ' . self::LONGUEUR_MIN_MOT_DE_PASSE . ' caracteres.',
            $motDePasse !== $confirmation => 'Les deux mots de passe ne correspondent pas.',
            default => null,
        };

        if ($erreur !== null) {
            Session::flash('error', $erreur);
            header('Location: /mot-de-passe/reinitialiser/' . rawurlencode($jeton));

            return;
        }

        if (!$this->resets->reinitialiserMotDePasse($jeton, $motDePasse)) {
            Session::flash('error', 'Ce lien de reinitialisation est invalide ou a expire. Faites une nouvelle demande.');
            header('Location: /mot-de-passe-oublie');

            return;
        }

        Session::flash('success', 'Mot de passe modifie. Vous pouvez vous connecter.');
        header('Location: /login');
    }
}
