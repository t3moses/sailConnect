<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * Assignment Response DTO
 *
 * Represents a crew member's assignment (or lack thereof) for a specific event.
 */
class AssignmentResponse
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventDate,
        public readonly string $startTime,
        public readonly string $finishTime,
        public readonly int $availabilityStatus,
        public readonly ?string $boatName,
        public readonly ?string $boatKey,
        public readonly array $crewmates,
        public readonly ?string $displayName = null,
    ) {
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'eventDate' => $this->eventDate,
            'startTime' => $this->startTime,
            'finishTime' => $this->finishTime,
            'availabilityStatus' => $this->availabilityStatus,
            'boatName' => $this->boatName,
            'boatKey' => $this->boatKey,
            'crewmates' => $this->crewmates,
            'displayName' => $this->displayName,
        ];
    }
}
