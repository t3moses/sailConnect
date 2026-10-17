<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Invalid Credentials Exception
 *
 * Thrown when user provides incorrect email or password during login.
 * Maps to HTTP 401 Unauthorized response.
 */
class InvalidCredentialsException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid email or password')
    {
        parent::__construct($message);
    }
}
