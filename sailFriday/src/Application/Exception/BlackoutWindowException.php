<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Blackout Window Exception
 *
 * Thrown when attempting to modify data during a blackout window (event day).
 */
class BlackoutWindowException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Registration is locked during the event blackout window');
    }
}
