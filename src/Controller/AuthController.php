<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    private Auth $auth;

    public function __construct()
    {
        $this->auth = new Auth();
    }

    public function showLogin(Request $request): void
    {
        if ($this->auth->check()) {
            header('Location: /tickets');

            return;
        }

        View::render('auth/login', ['error' => Session::pullFlash('error')]);
    }

    public function login(Request $request): void
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if ($this->auth->attempt($email, $password)) {
            header('Location: /tickets');

            return;
        }

        Session::flash('error', 'Identifiants incorrects.');
        header('Location: /login');
    }

    public function logout(Request $request): void
    {
        $this->auth->logout();
        header('Location: /login');
    }
}
