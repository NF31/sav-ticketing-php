<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Env;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repository\PasswordResetRepository;
use App\Repository\UserRepository;
use App\Validation\MotDePasse;

final class PasswordResetController
{
    // Par email : empeche d'inonder la boite d'une victime de liens.
    private const int MAX_DEMANDES_PAR_EMAIL = 3;
    // Par IP : empeche un meme poste de balayer une liste d'emails.
    private const int MAX_DEMANDES_PAR_IP = 10;
    private const int FENETRE_LIMITE_MINUTES = 60;

    /**
     * Message identique que l'email existe ou non : sinon le formulaire
     * permettrait de tester quels emails ont un compte (enumeration).
     */
    private const string MESSAGE_DEMANDE_ENVOYEE = 'Si un compte existe pour cet email, un lien de reinitialisation vient d\'etre envoye. Il est valable '
        . PasswordResetRepository::DUREE_VALIDITE_MINUTES . ' minutes.';

    private UserRepository $users;
    private PasswordResetRepository $resets;
    private Mailer $mailer;
    private RateLimiter $limiter;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->resets = new PasswordResetRepository();
        $this->mailer = new Mailer();
        $this->limiter = new RateLimiter();
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

        $cleIp = 'reset:ip:' . $request->ip();

        if ($this->limiter->tropDeTentatives($cleIp, self::MAX_DEMANDES_PAR_IP, self::FENETRE_LIMITE_MINUTES)) {
            // Rendu direct plutot qu'une redirection : header('Location')
            // remplacerait le statut 429 par un 302.
            http_response_code(429);
            View::render('auth/mot-de-passe-oublie', [
                'error' => 'Trop de demandes depuis votre connexion. Reessayez dans une heure.',
            ]);

            return;
        }

        // Email hashe dans la cle : la table tentatives ne conserve pas
        // d'adresses en clair.
        $cleEmail = 'reset:email:' . hash('sha256', strtolower($email));
        $limiteEmailAtteinte = $this->limiter->tropDeTentatives($cleEmail, self::MAX_DEMANDES_PAR_EMAIL, self::FENETRE_LIMITE_MINUTES);

        $this->limiter->enregistrer($cleIp);
        $this->limiter->enregistrer($cleEmail);

        $user = $this->users->findByEmail($email);

        // Limite par email atteinte : on n'envoie rien, mais on affiche le
        // meme message que d'habitude. Un message different revelerait
        // que des demandes ont deja ete faites, donc que le compte existe.
        if ($user !== null && !$limiteEmailAtteinte) {
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
            'longueurMin' => MotDePasse::LONGUEUR_MIN,
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function reinitialiser(Request $request): void
    {
        $jeton = $request->param('token');
        // Pas de trim() : un espace en debut/fin de mot de passe est volontaire.
        $motDePasse = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        $erreur = MotDePasse::erreur($motDePasse)
            ?? ($motDePasse !== $confirmation ? 'Les deux mots de passe ne correspondent pas.' : null);

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
