<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal front-controller : une seule entree (public/index.php),
 * contrairement a l'original ou chaque action etait un fichier PHP separe
 * directement accessible (controler/tickets.php?action=...).
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, paramNames: list<string>, handler: array{0: string, 1: string}, requiresAuth: bool, requiresAdmin: bool}> */
    private array $routes = [];

    public function get(string $pattern, array $handler, bool $auth = false, bool $admin = false): void
    {
        $this->add('GET', $pattern, $handler, $auth, $admin);
    }

    public function post(string $pattern, array $handler, bool $auth = false, bool $admin = false): void
    {
        $this->add('POST', $pattern, $handler, $auth, $admin);
    }

    private function add(string $method, string $pattern, array $handler, bool $auth, bool $admin): void
    {
        $paramNames = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function (array $m) use (&$paramNames): string {
            $paramNames[] = $m[1];

            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'paramNames' => $paramNames,
            'handler' => $handler,
            'requiresAuth' => $auth,
            'requiresAdmin' => $admin,
        ];
    }

    public function dispatch(Request $request, Auth $authService): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['paramNames'] as $name) {
                $params[$name] = $matches[$name];
            }

            if ($route['requiresAuth'] && !$authService->check()) {
                Session::flash('error', 'Merci de vous connecter.');
                header('Location: /login');

                return;
            }

            if ($route['requiresAdmin'] && !$authService->isAdmin()) {
                http_response_code(403);
                echo 'Acces reserve aux administrateurs.';

                return;
            }

            [$controllerClass, $action] = $route['handler'];
            $controller = new $controllerClass();
            $controller->$action($request->withRouteParams($params));

            return;
        }

        http_response_code(404);
        echo 'Page introuvable.';
    }
}
