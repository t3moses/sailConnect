<?php

declare(strict_types=1);

namespace App\Application\Exception;

use App\Domain\ValueObject\EventId;

/**
 * Event Not Found Exception
 *
 * Thrown when an event cannot be found in the repository.
 */
class EventNotFoundException extends \RuntimeException
{
    public function __construct(EventId $eventId)
    {
        parent::__construct("Event not found: {$eventId->toString()}");
    }
}
