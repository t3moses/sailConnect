<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * User Already Exists Exception
 *
 * Thrown when attempting to register a user with an email that already exists.
 * Maps to HTTP 409 Conflict response.
 */
class UserAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('User with email "%s" already exists', $email));
    }
}
