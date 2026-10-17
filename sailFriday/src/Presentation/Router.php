<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\Response\JsonResponse;

/**
 * Simple Router
 *
 * Matches HTTP requests to routes and dispatches to controllers.
 */
class Router
{
    private array $routes = [];

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Match request to a route
     *
     * @return array|null Route data or null if no match
     */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertRouteToRegex($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                // Extract route parameters
                $params = [];
                if (preg_match_all('/\{(\w+)\}/', $route['path'], $paramNames)) {
                    foreach ($paramNames[1] as $index => $name) {
                        $params[$name] = urldecode($matches[$index + 1] ?? '');
                    }
                }

                return [
                    'controller' => $route['controller'],
                    'action' => $route['action'],
                    'auth' => $route['auth'] ?? false,
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Convert route path to regex pattern
     */
    private function convertRouteToRegex(string $path): string
    {
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $path);

        // Replace {param} with capturing group
        $pattern = preg_replace('/\{(\w+)\}/', '([^\/]+)', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Create 404 Not Found response
     */
    public function notFound(): JsonResponse
    {
        return JsonResponse::error('Route not found', 404);
    }

    /**
     * Create 405 Method Not Allowed response
     */
    public function methodNotAllowed(): JsonResponse
    {
        return JsonResponse::error('Method not allowed', 405);
    }
}
