<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    // Par couple email + IP : bloque la force brute sur un compte.
    private const int MAX_ECHECS_PAR_COMPTE = 5;
    // Par IP seule : bloque l'essai d'un meme mot de passe sur beaucoup
    // de comptes differents (password spraying).
    private const int MAX_ECHECS_PAR_IP = 20;
    private const int FENETRE_LIMITE_MINUTES = 15;

    private Auth $auth;
    private RateLimiter $limiter;

    public function __construct()
    {
        $this->auth = new Auth();
        $this->limiter = new RateLimiter();
    }

    public function showLogin(Request $request): void
    {
        if ($this->auth->check()) {
            header('Location: /tickets');

            return;
        }

        View::render('auth/login', [
            'error' => Session::pullFlash('error'),
            'success' => Session::pullFlash('success'),
        ]);
    }

    public function login(Request $request): void
    {
        $email = $request->input('email');
        $password = $request->input('password');

        // Les echecs sont comptes que l'email existe ou non : le blocage ne
        // revele donc pas si le compte existe.
        $cleCompte = 'login:compte:' . hash('sha256', strtolower($email) . '|' . $request->ip());
        $cleIp = 'login:ip:' . $request->ip();

        if (
            $this->limiter->tropDeTentatives($cleCompte, self::MAX_ECHECS_PAR_COMPTE, self::FENETRE_LIMITE_MINUTES)
            || $this->limiter->tropDeTentatives($cleIp, self::MAX_ECHECS_PAR_IP, self::FENETRE_LIMITE_MINUTES)
        ) {
            // Rendu direct : header('Location') remplacerait le 429 par un 302.
            http_response_code(429);
            View::render('auth/login', [
                'error' => 'Trop de tentatives de connexion. Reessayez dans ' . self::FENETRE_LIMITE_MINUTES . ' minutes.',
                'success' => null,
            ]);

            return;
        }

        if ($this->auth->attempt($email, $password)) {
            // Le compteur par IP n'est pas remis a zero : un attaquant qui
            // possede un compte ne doit pas pouvoir s'en servir pour effacer
            // ses echecs sur les autres.
            $this->limiter->effacer($cleCompte);
            header('Location: /tickets');

            return;
        }

        $this->limiter->enregistrer($cleCompte);
        $this->limiter->enregistrer($cleIp);

        Session::flash('error', 'Identifiants incorrects.');
        header('Location: /login');
    }

    public function logout(Request $request): void
    {
        $this->auth->logout();
        header('Location: /login');
    }
}
