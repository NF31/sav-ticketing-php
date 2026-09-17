<?php

declare(strict_types=1);

namespace App\Core;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;

final class Auth
{
    private const string SESSION_KEY = 'user_id';

    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user->passwordHash)) {
            return false;
        }

        // Nouvel identifiant de session apres authentification : evite la
        // fixation de session (un attaquant qui aurait fixe un session id
        // avant le login ne recupere pas une session desormais authentifiee).
        Session::regenerate();
        Session::set(self::SESSION_KEY, $user->id);

        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function user(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return is_int($id) ? $this->users->findById($id) : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return $this->user()?->role === Role::Admin;
    }
}
