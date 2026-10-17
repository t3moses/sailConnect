<?php

declare(strict_types=1);

namespace App\Application\UseCase\Boat;

use App\Application\Exception\BoatNotFoundException;
use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\SeasonRepositoryInterface;
use App\Application\UseCase\Crew\RecordNoShowUseCase;
use App\Domain\ValueObject\EventId;
use Psr\Log\LoggerInterface;

/**
 * Flag Assigned Crew Use Case
 *
 * Lets a boat owner flag crew members who were assigned to their boat for one
 * or more events. Each verified (event, crew) pair is recorded as a no-show
 * via RecordNoShowUseCase, which derives commitment rank from the crew's
 * total no-show count, withdraws them from all future events, and
 * deactivates the account once commitment rank hits 0. An inactive crew is
 * blocked from updating their own availability (see
 * UpdateCrewAvailabilityUseCase).
 *
 * SECURITY: Client-submitted (eventId, crewKey) pairs are never trusted at
 * face value. Each pair is independently verified against the actual
 * persisted flotilla for that event before it is recorded, so a boat owner
 * can only flag crew who were genuinely assigned to their own boat. Flags
 * are also restricted to past events only — the next event's assignment can
 * still change before it happens, so it isn't eligible for flagging yet.
 */
class FlagAssignedCrewUseCase
{
    public function __construct(
        private BoatRepositoryInterface $boatRepository,
        private EventRepositoryInterface $eventRepository,
        private SeasonRepositoryInterface $seasonRepository,
        private RecordNoShowUseCase $recordNoShowUseCase,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param int $userId Authenticated boat owner's user ID
     * @param array<int, array{eventId: string, crewKey: string}> $flags
     * @return array<int, array{
     *     crew_key: string,
     *     display_name: ?string,
     *     no_show_count: int,
     *     rank_commitment: int,
     *     active: bool,
     *     withdrawn_from_future_events: bool
     * }>
     * @throws BoatNotFoundException
     */
    public function execute(int $userId, array $flags): array
    {
        $boat = $this->boatRepository->findByOwnerUserId($userId);
        if ($boat === null) {
            throw new BoatNotFoundException("No boat found for user ID: {$userId}");
        }

        // Only events that have already happened are eligible for flagging.
        $pastEventIds = array_flip($this->eventRepository->findPastEvents());

        // Verify each (eventId, crewKey) pair against the real persisted flotilla,
        // de-duplicating so a repeated pair can't be recorded more than once.
        $verifiedPairs = [];
        foreach ($flags as $flag) {
            $pairKey = $flag['eventId'] . '|' . $flag['crewKey'];
            if (isset($verifiedPairs[$pairKey])) {
                continue;
            }
            if (!isset($pastEventIds[$flag['eventId']])) {
                continue;
            }
            if ($this->wasAssignedToBoat($flag['eventId'], $flag['crewKey'], $boat->getKey()->toString())) {
                $verifiedPairs[$pairKey] = $flag;
            }
        }

        // Record each verified pair, keeping the latest result per crew - the
        // recomputed commitment rank reflects the crew's total no-show count
        // regardless of how many pairs (or in what order) were processed.
        $resultsByCrewKey = [];
        foreach ($verifiedPairs as $flag) {
            $result = $this->recordNoShowUseCase->execute($flag['crewKey'], $flag['eventId']);
            $resultsByCrewKey[$flag['crewKey']] = $result;

            $this->logger->info('boat_owner.crew_flagged', [
                'boat_key' => $boat->getKey()->toString(),
                'crew_key' => $flag['crewKey'],
                'event_id' => $flag['eventId'],
            ]);
        }

        return array_values($resultsByCrewKey);
    }

    /**
     * Check the persisted flotilla for $eventId to see whether $crewKeyString was
     * actually assigned to the boat identified by $boatKeyString.
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
