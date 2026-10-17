<?php

declare(strict_types=1);

namespace App\Application\UseCase\Season;

use App\Application\Exception\EventNotFoundException;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\SeasonRepositoryInterface;
use App\Domain\ValueObject\EventId;

/**
 * Generate Flotilla Use Case
 *
 * Retrieves or generates flotilla data for a specific event.
 * This is used to get assignment details for display.
 */
class GenerateFlotillaUseCase
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private SeasonRepositoryInterface $seasonRepository,
    ) {
    }

    /**
     * Execute the use case
     *
     * @param EventId $eventId
     * @return array{event_id: string, crewed_boats: array, waitlist_boats: array, waitlist_crews: array}
     * @throws EventNotFoundException
     */
    public function execute(EventId $eventId): array
    {
        // Verify event exists
        $eventData = $this->eventRepository->findById($eventId);
        if ($eventData === null) {
            throw new EventNotFoundException($eventId);
        }

        // Get flotilla data
        $flotilla = $this->seasonRepository->getFlotilla($eventId);

        // If no flotilla exists, return empty structure
        if ($flotilla === null) {
            return [
                'event_id' => $eventId->toString(),
                'crewed_boats' => [],
                'waitlist_boats' => [],
                'waitlist_crews' => [],
            ];
        }

        return $flotilla;
    }
}
