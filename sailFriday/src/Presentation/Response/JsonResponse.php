<?php

declare(strict_types=1);

namespace App\Presentation\Response;

/**
 * JSON Response Wrapper
 *
 * Standardizes API responses with consistent structure.
 */
class JsonResponse
{
    public function __construct(
        private mixed $data,
        private int $statusCode = 200,
        private array $headers = []
    ) {
    }

    /**
     * Send the JSON response
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        header('Content-Type: application/json');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send JSON body
        echo json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Create success response
     */
    public static function success(mixed $data, int $statusCode = 200): self
    {
        return new self([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Create error response
     */
    public static function error(string $message, int $statusCode = 400, ?array $details = null): self
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($details !== null) {
            $response['details'] = $details;
        }

        return new self($response, $statusCode);
    }

    /**
     * Create 404 Not Found response
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, 404);
    }

    /**
     * Create 500 Internal Server Error response
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::error($message, 500);
    }
}
