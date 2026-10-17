<?php

declare(strict_types=1);

namespace App\Application\UseCase\Boat;

use App\Application\Exception\BoatNotFoundException;
use App\Application\Exception\CrewNotFoundException;
use App\Application\Exception\ValidationException;
use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\SeasonRepositoryInterface;
use App\Domain\ValueObject\CrewKey;
use App\Domain\ValueObject\EventId;
use Psr\Log\LoggerInterface;

/**
 * Remove Assigned Crew From Whitelist Use Case
 *
 * Lets a boat owner remove their own boat from the whitelist of a crew
 * member who was assigned to their boat for a past event.
 *
 * SECURITY: Mirrors FlagAssignedCrewUseCase and UpdateAssignedCrewSkillUseCase
 * - the (eventId, crewKey) pair is verified against the persisted flotilla
 * for that event before the whitelist change is applied, and only past
 * events are eligible.
 */
class RemoveAssignedCrewFromWhitelistUseCase
{
    public function __construct(
        private BoatRepositoryInterface $boatRepository,
        private CrewRepositoryInterface $crewRepository,
        private EventRepositoryInterface $eventRepository,
        private SeasonRepositoryInterface $seasonRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{crew_key: string, boat_key: string}
     * @throws BoatNotFoundException
     * @throws CrewNotFoundException
     * @throws ValidationException
     */
    public function execute(int $userId, string $eventIdString, string $crewKeyString): array
    {
        $boat = $this->boatRepository->findByOwnerUserId($userId);
        if ($boat === null) {
            throw new BoatNotFoundException("No boat found for user ID: {$userId}");
        }

        $pastEventIds = array_flip($this->eventRepository->findPastEvents());
        if (!isset($pastEventIds[$eventIdString])) {
            throw new ValidationException(['eventId' => 'Whitelist can only be changed for a past event']);
        }

        if (!$this->wasAssignedToBoat($eventIdString, $crewKeyString, $boat->getKey()->toString())) {
            throw new ValidationException(['crewKey' => 'Crew member was not assigned to your boat for that event']);
        }

        $crewKey = CrewKey::fromString($crewKeyString);
        $crew = $this->crewRepository->findByKey($crewKey);
        if ($crew === null) {
            throw new CrewNotFoundException("Crew not found: {$crewKeyString}");
        }

        $this->crewRepository->removeFromWhitelist($crewKey, $boat->getKey());

        $this->logger->info('boat_owner.whitelist_removed', [
            'boat_key' => $boat->getKey()->toString(),
            'crew_key' => $crewKeyString,
            'event_id' => $eventIdString,
        ]);

        return [
            'crew_key' => $crewKeyString,
            'boat_key' => $boat->getKey()->toString(),
        ];
    }

    /**
     * Check the persisted flotilla for $eventIdString to see whether
     * $crewKeyString was actually assigned to the boat identified by $boatKeyString.
     */
    private function wasAssignedToBoat(string $eventIdString, string $crewKeyString, string $boatKeyString): bool
    {
        if (!$this->eventRepository->exists(EventId::fromString($eventIdString))) {
            return false;
        }

        $flotilla = $this->seasonRepository->getFlotilla(EventId::fromString($eventIdString));
        if ($flotilla === null) {
            return false;
        }

        foreach ($flotilla['crewed_boats'] as $crewedBoat) {
            if ($crewedBoat['boat']['key'] !== $boatKeyString) {
                continue;
            }
            foreach ($crewedBoat['crews'] as $crew) {
                if ($crew['key'] === $crewKeyString) {
                    return true;
                }
            }
        }

        return false;
    }
}
