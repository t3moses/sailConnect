<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Invalid Token Exception
 *
 * Thrown when JWT token is invalid, expired, or malformed.
 * Maps to HTTP 401 Unauthorized response.
 */
class InvalidTokenException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid or expired token')
    {
        parent::__construct($message);
    }
}
