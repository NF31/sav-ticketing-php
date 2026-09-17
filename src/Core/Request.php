<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @param array<string, string> $routeParams */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $routeParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self($method, rtrim($path, '/') ?: '/');
    }

    public function withRouteParams(array $params): self
    {
        return new self($this->method, $this->path, $params);
    }

    public function param(string $key): string
    {
        return $this->routeParams[$key] ?? '';
    }

    public function input(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
    }

    public function intInput(string $key): ?int
    {
        $value = $_POST[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
