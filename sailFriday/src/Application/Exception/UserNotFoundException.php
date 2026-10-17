<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * User Not Found Exception
 *
 * Thrown when a user cannot be found in the repository.
 */
class UserNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'User not found')
    {
        parent::__construct($message);
    }
}
