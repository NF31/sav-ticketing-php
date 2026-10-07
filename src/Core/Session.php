<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                // Cookie envoye uniquement en HTTPS quand l'app y est servie
                // (pas en dev sur http://localhost, ou il ne serait jamais renvoye).
                'cookie_secure' => self::enHttps(),
                // Refuse un identifiant de session que le serveur n'a pas cree
                // lui-meme : un attaquant ne peut pas imposer le sien a la victime
                // (complete Session::regenerate() a la connexion).
                'use_strict_mode' => true,
            ]);
        }
    }

    public static function enHttps(): bool
    {
        return str_starts_with((string) Env::get('APP_URL', ''), 'https://');
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function pullFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $message;
    }

    public static function regenerate(): void
    {
        // Sans session active (ex. tests en CLI), rien a regenerer.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
