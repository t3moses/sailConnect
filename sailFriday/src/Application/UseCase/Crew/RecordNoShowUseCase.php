<?php

declare(strict_types=1);

namespace App\Application\UseCase\Crew;

use App\Application\Exception\CrewNotFoundException;
use App\Application\Exception\ValidationException;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Service\TransactionServiceInterface;
use App\Domain\Enum\CrewRankDimension;
use App\Domain\ValueObject\CrewKey;
use App\Domain\ValueObject\EventId;
use Psr\Log\LoggerInterface;

/**
 * Record No Show Use Case
 *
 * Records a no-show for a crew member at a past event and applies its
 * consequences. Shared by both entry points that can record a no-show: a
 * boat owner flagging a crewmate assigned to their boat (see
 * FlagAssignedCrewUseCase, which verifies the pair against the persisted
 * flotilla before calling this) and an admin recording one directly from the
 * crew's profile.
 *
 * Commitment rank is derived, not decremented: after inserting the no_shows
 * row, it is recomputed as initial_commitment_rank minus the crew's total
 * no_shows count, floored at 0. Every call also withdraws the crew from all
 * future events, and deactivates the account if the recomputed rank hits 0.
 */
class RecordNoShowUseCase
{
    public function __construct(
        private CrewRepositoryInterface $crewRepository,
        private EventRepositoryInterface $eventRepository,
        private TransactionServiceInterface $transactionService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     crew_key: string,
     *     display_name: ?string,
     *     no_show_count: int,
     *     rank_commitment: int,
     *     active: bool,
     *     withdrawn_from_future_events: bool
     * }
     * @throws CrewNotFoundException
     * @throws ValidationException
     */
    public function execute(string $crewKeyString, string $eventIdString): array
    {
        $crew = $this->crewRepository->findByKey(CrewKey::fromString($crewKeyString));
        if ($crew === null) {
            throw new CrewNotFoundException("Crew not found: {$crewKeyString}");
        }

        $pastEventIds = array_flip($this->eventRepository->findPastEvents());
        if (!isset($pastEventIds[$eventIdString])) {
            throw new ValidationException(['event_id' => 'Must be a past event']);
        }

        $eventId = EventId::fromString($eventIdString);

        $this->transactionService->begin();
        try {
            $this->crewRepository->recordNoShow($crew->getKey(), $eventId);

            $noShowCount = $this->crewRepository->countNoShows($crew->getKey());
            $rankAfter = max(0, $crew->getInitialCommitmentRank() - $noShowCount);
            $crew->setRankDimension(CrewRankDimension::COMMITMENT, $rankAfter);
            $this->crewRepository->updateRankCommitment($crew);

            $withdrawnFromFutureEvents = false;
            $futureEventIds = $this->eventRepository->findFutureEvents();
            if (!empty($futureEventIds)) {
                $this->crewRepository->deleteAvailabilityForEvents($crew->getKey(), $futureEventIds);
                $withdrawnFromFutureEvents = true;
            }

            if ($rankAfter === 0) {
                $crew->setActive(false);
                $this->crewRepository->updateActive($crew);
            }

            $this->transactionService->commit();
        } catch (\Throwable $e) {
            $this->transactionService->rollBack();
            throw $e;
        }

        $this->logger->info('no_show.recorded', [
            'crew_key'                     => $crewKeyString,
            'event_id'                     => $eventIdString,
            'no_show_count'                => $noShowCount,
            'rank_after'                   => $rankAfter,
            'deactivated'                  => $rankAfter === 0,
            'withdrawn_from_future_events' => $withdrawnFromFutureEvents,
        ]);

        return [
            'crew_key'                     => $crewKeyString,
            'display_name'                 => $crew->getDisplayName(),
            'no_show_count'                => $noShowCount,
            'rank_commitment'              => $rankAfter,
            'active'                       => $crew->isActive(),
            'withdrawn_from_future_events' => $withdrawnFromFutureEvents,
        ];
    }
}
