<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Application\Exception\ValidationException;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\TransactionServiceInterface;
use App\Domain\Enum\CrewRankDimension;
use Psr\Log\LoggerInterface;

/**
 * Deactivate User Use Case
 *
 * Deactivates a user's linked crew account. The user row and every other table
 * are left intact, so the account keeps existing (and the email address stays
 * claimed, blocking re-registration). The consequences mirror what happens when
 * a crew is flagged as a no-show while their commitment rank is already 0:
 *
 *   - every future-event crew_availability row for the crew is deleted
 *   - crews.active is set to false
 *   - crews.commitment_rank is set to 0
 *
 * Prevents admins from deactivating their own account.
 */
class DeactivateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CrewRepositoryInterface $crewRepository,
        private EventRepositoryInterface $eventRepository,
        private TransactionServiceInterface $transactionService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Execute the use case
     *
     * @param int $targetUserId     ID of the user to deactivate
     * @param int $requestingUserId ID of the admin making the change
     * @return array{deactivated: bool, user_id: int, crew_key: string, withdrawn_from_future_events: bool}
     * @throws ValidationException If an admin targets their own account, or the user has no linked crew
     * @throws \RuntimeException   If the target user is not found
     */
    public function execute(int $targetUserId, int $requestingUserId): array
    {
        if ($targetUserId === $requestingUserId) {
            throw new ValidationException(['user_id' => 'You cannot deactivate your own account']);
        }

        $user = $this->userRepository->findById($targetUserId);

        if ($user === null) {
            throw new \RuntimeException("User with ID {$targetUserId} not found");
        }

        $crew = $this->crewRepository->findByUserId($targetUserId);

        if ($crew === null) {
            throw new ValidationException(['user_id' => 'This account has no linked crew profile to deactivate']);
        }

        $this->transactionService->begin();

        try {
            $withdrawnFromFutureEvents = false;
            $futureEventIds = $this->eventRepository->findFutureEvents();
            if (!empty($futureEventIds)) {
                $this->crewRepository->deleteAvailabilityForEvents($crew->getKey(), $futureEventIds);
                $withdrawnFromFutureEvents = true;
            }

            $crew->setRankDimension(CrewRankDimension::COMMITMENT, 0);
            $this->crewRepository->updateRankCommitment($crew);

            $crew->setActive(false);
            $this->crewRepository->updateActive($crew);

            $this->transactionService->commit();
        } catch (\Throwable $e) {
            $this->transactionService->rollBack();
            throw $e;
        }

        $this->logger->info('admin.user_deactivated', [
            'user_id'                     => $targetUserId,
            'email'                       => $user->getEmail(),
            'crew_key'                    => $crew->getKey()->toString(),
            'deactivated_by_user_id'      => $requestingUserId,
            'withdrawn_from_future_events' => $withdrawnFromFutureEvents,
        ]);

        return [
            'deactivated'                 => true,
            'user_id'                     => $targetUserId,
            'crew_key'                    => $crew->getKey()->toString(),
            'withdrawn_from_future_events' => $withdrawnFromFutureEvents,
        ];
    }
}
