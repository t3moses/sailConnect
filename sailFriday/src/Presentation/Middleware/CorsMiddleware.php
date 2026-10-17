<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) headers.
 * Allows frontend applications to access the API from different origins.
 */
class CorsMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-User-FirstName', 'X-User-LastName'],
            'max_age' => 86400, // 24 hours
        ], $config);
    }

    /**
     * Apply CORS headers
     */
    public function apply(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
        } elseif (in_array('*', $this->config['allowed_origins'])) {
            header('Access-Control-Allow-Origin: *');
        }

        // Allow credentials
        header('Access-Control-Allow-Credentials: true');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $this->config['allowed_methods']));
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->config['allowed_headers']));
            header('Access-Control-Max-Age: ' . $this->config['max_age']);
            http_response_code(204);
            exit;
        }
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        return in_array($origin, $this->config['allowed_origins']);
    }
}
