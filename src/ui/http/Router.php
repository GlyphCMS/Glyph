<?php

declare(strict_types=1);

namespace Glyph\ui\http;

use Glyph\adapters\http\Request;
use Glyph\adapters\http\Response;

final class Router
{
    /**
     * @var array<string, array<string, callable(Request): Response>>
     */
    private array $routes = [];

    /**
     * @var null|callable(Request): Response
     */
    private $fallbackAction = null;

    public function get(string $path, callable $action): void
    {
        $this->addRoute('GET', $path, $action);
    }

    public function post(string $path, callable $action): void
    {
        $this->addRoute('POST', $path, $action);
    }

    public function fallback(callable $action): void
    {
        $this->fallbackAction = $action;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $action = $this->routes[$method][$path] ?? null;

        if ($action !== null) {
            $response = $action($request);

            if (!$response instanceof Response) {
                throw new \RuntimeException('Route action must return a Response instance.');
            }

            return $response;
        }

        if ($this->fallbackAction !== null) {
            $response = ($this->fallbackAction)($request);

            if (!$response instanceof Response) {
                throw new \RuntimeException('Fallback action must return a Response instance.');
            }

            return $response;
        }

        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>404</h1><p>Page not found.</p></body></html>',
            404,
        );
    }

    private function addRoute(string $method, string $path, callable $action): void
    {
        $normalizedPath = '/' . trim($path, '/');

        if ($normalizedPath === '//') {
            $normalizedPath = '/';
        }

        $this->routes[$method][$normalizedPath] = $action;
    }
}