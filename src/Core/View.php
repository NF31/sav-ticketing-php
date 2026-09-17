<?php

declare(strict_types=1);

namespace App\Core;

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
