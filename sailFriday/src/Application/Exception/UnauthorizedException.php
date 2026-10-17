<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Unauthorized Exception
 *
 * Thrown when user attempts to access a resource they don't have permission for.
 * Maps to HTTP 403 Forbidden response.
 */
class UnauthorizedException extends \RuntimeException
{
    public function __construct(string $message = 'Access forbidden')
    {
        parent::__construct($message);
    }
}
