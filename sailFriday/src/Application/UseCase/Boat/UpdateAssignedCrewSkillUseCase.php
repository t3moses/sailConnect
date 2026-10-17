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
use App\Domain\Enum\SkillLevel;
use App\Domain\ValueObject\CrewKey;
use App\Domain\ValueObject\EventId;
use Psr\Log\LoggerInterface;

/**
 * Update Assigned Crew Skill Use Case
 *
 * Lets a boat owner correct the recorded skill level of a crew member who
 * was assigned to their boat for a past event, once they've actually sailed
 * together and the owner can assess it firsthand.
 *
 * SECURITY: Mirrors FlagAssignedCrewUseCase - the (eventId, crewKey) pair is
 * verified against the persisted flotilla for that event before the update
 * is applied, and only past events are eligible.
 */
class UpdateAssignedCrewSkillUseCase
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
     * @return array{crew_key: string, skill: int}
     * @throws BoatNotFoundException
     * @throws CrewNotFoundException
     * @throws ValidationException
     */
    public function execute(int $userId, string $eventIdString, string $crewKeyString, int $skill): array
    {
        if (!in_array($skill, [0, 1, 2], true)) {
            throw new ValidationException(['skill' => 'Skill must be 0 (Novice), 1 (Competent crew), or 2 (Competent first mate)']);
        }

        $boat = $this->boatRepository->findByOwnerUserId($userId);
        if ($boat === null) {
            throw new BoatNotFoundException("No boat found for user ID: {$userId}");
        }

        $pastEventIds = array_flip($this->eventRepository->findPastEvents());
        if (!isset($pastEventIds[$eventIdString])) {
            throw new ValidationException(['eventId' => 'Skill can only be corrected for a past event']);
        }

        if (!$this->wasAssignedToBoat($eventIdString, $crewKeyString, $boat->getKey()->toString())) {
            throw new ValidationException(['crewKey' => 'Crew member was not assigned to your boat for that event']);
        }

        $crew = $this->crewRepository->findByKey(CrewKey::fromString($crewKeyString));
        if ($crew === null) {
            throw new CrewNotFoundException("Crew not found: {$crewKeyString}");
        }

        $crew->setSkill(SkillLevel::from($skill));
        $this->crewRepository->save($crew);

        $this->logger->info('boat_owner.crew_skill_updated', [
            'boat_key' => $boat->getKey()->toString(),
            'crew_key' => $crewKeyString,
            'event_id' => $eventIdString,
            'skill'    => $skill,
        ]);

        return [
            'crew_key' => $crewKeyString,
            'skill'    => $skill,
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
