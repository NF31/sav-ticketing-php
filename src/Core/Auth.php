<?php

declare(strict_types=1);

namespace App\Core;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;

final class Auth
{
    private const string SESSION_KEY = 'user_id';
    private const string SESSION_EMPREINTE = 'user_empreinte_mdp';

    /**
     * Hash bcrypt d'une valeur aleatoire jetee, de meme cout que les vrais
     * hash : verifie quand l'email n'existe pas, pour que la reponse prenne
     * autant de temps que pour un compte existant. Sans ca, une reponse
     * plus rapide trahirait que l'email n'a pas de compte (enumeration).
     */
    private const string HASH_FACTICE = '$2y$12$g4SuuExKE2FX8QGEQpBXsuti4.GoYlQYwJfjx42IaXioOT5YQFOIa';

    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            password_verify($password, self::HASH_FACTICE);

            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return false;
        }

        // Nouvel identifiant de session apres authentification : evite la
        // fixation de session (un attaquant qui aurait fixe un session id
        // avant le login ne recupere pas une session desormais authentifiee).
        Session::regenerate();
        Csrf::regenerer();
        Session::set(self::SESSION_KEY, $user->id);
        Session::set(self::SESSION_EMPREINTE, self::empreinte($user));

        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * La session n'est valide que si le mot de passe n'a pas change depuis
     * la connexion : apres une reinitialisation, toutes les sessions deja
     * ouvertes sur le compte (dont celle d'un eventuel attaquant) sont
     * deconnectees, sans table de sessions a maintenir.
     */
    public function user(): ?User
    {
        $id = Session::get(self::SESSION_KEY);
        $user = is_int($id) ? $this->users->findById($id) : null;

        if ($user === null) {
            return null;
        }

        $empreinte = Session::get(self::SESSION_EMPREINTE);

        if (!is_string($empreinte) || !hash_equals(self::empreinte($user), $empreinte)) {
            Session::remove(self::SESSION_KEY);
            Session::remove(self::SESSION_EMPREINTE);

            return null;
        }

        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return $this->user()?->role === Role::Admin;
    }

    /** Derivee du hash (qui change a chaque nouveau mot de passe), sans le stocker tel quel en session. */
    private static function empreinte(User $user): string
    {
        return hash('sha256', $user->passwordHash);
    }
}
