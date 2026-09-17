<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rendu en deux passes (template capture d'abord, puis layout) plutot que
 * de require directement le layout depuis le template : ca permet au
 * layout de decider ou placer $content (avant/apres la nav, etc.) sans
 * que chaque template ait a connaitre la structure du layout.
 */
final class View
{
    private const string TEMPLATES_DIR = __DIR__ . '/../../templates';

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        $content = self::capture($template, $data);
        echo self::capture('layout/base', ['content' => $content, 'auth' => new Auth()]);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require self::TEMPLATES_DIR . '/' . $template . '.php';

        return (string) ob_get_clean();
    }
}
