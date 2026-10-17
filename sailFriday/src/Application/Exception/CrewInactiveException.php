<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Crew Inactive Exception
 *
 * Thrown when an inactive crew member attempts to update their availability.
 */
class CrewInactiveException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Updates to your availability are blocked at this time.');
    }
}
