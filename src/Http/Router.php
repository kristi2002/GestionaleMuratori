<?php
declare(strict_types=1);

namespace App\Http;

use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Tiny method+path router. Supports {param} placeholders (matched as [^/]+)
 * which are passed to the handler after the Request.
 */
final class Router
{
    /** @var array<string,array<int,array{0:string,1:array{0:class-string,1:string}}>> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][] = [$path, $handler];
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][] = [$path, $handler];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes[$request->method] ?? [] as [$pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $pattern) . '$#';
            if (preg_match($regex, $request->path, $matches)) {
                array_shift($matches);
                [$class, $method] = $handler;
                (new $class())->$method($request, ...$matches);
                return;
            }
        }

        Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
    }
}
