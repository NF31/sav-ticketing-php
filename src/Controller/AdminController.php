<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Enum\Poste;
use App\Enum\Role;
use App\Repository\UserRepository;

final class AdminController
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function techniciens(Request $request): void
    {
        View::render('admin/techniciens', [
            'utilisateurs' => $this->users->findAll(),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function creerTechnicien(Request $request): void
    {
        $nom = $request->input('nom');
        $prenom = $request->input('prenom');
        $email = $request->input('email');
        $password = $request->input('password');
        $poste = Poste::tryFrom($request->input('poste'));

        if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $poste === null) {
            Session::flash('error', 'Champs invalides (mot de passe : 8 caracteres minimum).');
            header('Location: /admin/techniciens');

            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (nom, prenom, email, password_hash, role, poste) VALUES (:nom, :prenom, :email, :hash, :role, :poste)'
        );
        $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => Role::Technicien->value,
            'poste' => $poste->value,
        ]);

        header('Location: /admin/techniciens');
    }
}
