<?php

declare(strict_types=1);

namespace App\Application\Exception;

use App\Domain\ValueObject\EventId;

/**
 * Flotilla Not Found Exception
 *
 * Thrown when a flotilla cannot be found for a given event.
 */
class FlotillaNotFoundException extends \RuntimeException
{
    public function __construct(EventId $eventId)
    {
        parent::__construct("Flotilla not found for event: {$eventId->toString()}");
    }
}
