<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Crew Not Found Exception
 *
 * Thrown when a crew member cannot be found in the repository.
 */
class CrewNotFoundException extends \RuntimeException
{
    public function __construct(string $message = "Crew member not found")
    {
        parent::__construct($message);
    }
}
