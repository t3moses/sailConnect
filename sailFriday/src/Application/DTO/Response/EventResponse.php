<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * Event Response DTO
 *
 * Data transfer object for event information.
 */
final readonly class EventResponse
{
    public function __construct(
        public string $eventId,
        public string $date,
        public string $startTime,
        public string $finishTime,
        public string $status,
    ) {
    }

    /**
     * Create from event data
     *
     * @param array<string, mixed> $eventData
     */
    public static function fromArray(array $eventData): self
    {
        return new self(
            eventId: $eventData['event_id'],
            date: $eventData['event_date'],
            startTime: $eventData['start_time'],
            finishTime: $eventData['finish_time'],
            status: $eventData['status'] ?? 'upcoming',
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'date' => $this->date,
            'startTime' => $this->startTime,
            'finishTime' => $this->finishTime,
            'status' => $this->status,
        ];
    }
}
