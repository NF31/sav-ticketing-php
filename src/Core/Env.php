<?php

declare(strict_types=1);

namespace App\Core;

final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_file($path)) {
            self::$loaded = true;

            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = self::valeur($value);
        }

        self::$loaded = true;
    }

    /**
     * Retire les guillemets autour d'une valeur (CLE="valeur" ou CLE='valeur'),
     * ecriture courante dans les .env : sans ca, ils feraient partie de la
     * valeur (une cle API "re_..." serait envoyee avec ses guillemets).
     */
    public static function valeur(string $brute): string
    {
        $valeur = trim($brute);

        if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && $valeur[-1] === $valeur[0]) {
            return substr($valeur, 1, -1);
        }

        return $valeur;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
