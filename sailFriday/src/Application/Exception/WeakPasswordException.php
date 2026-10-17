<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Weak Password Exception
 *
 * Thrown when password does not meet security requirements.
 * Maps to HTTP 400 Bad Request response.
 */
class WeakPasswordException extends \RuntimeException
{
    public function __construct(string $message = 'Password does not meet security requirements')
    {
        parent::__construct($message);
    }
}
