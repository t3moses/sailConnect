<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Domain\Entity\Crew;
use App\Domain\Entity\User;
use App\Domain\Enum\CrewRankDimension;

/**
 * Sync Identity Use Case
 *
 * sailConnect (auth) owns email address and membership. sailFriday keeps local copies because
 * notifications and the crew ranking read them. This copies changes across for one user.
 *
 * Names are deliberately not synced: a crew key is derived from the name and keys are referenced
 * by partners, availability and history.
 */
class SyncIdentityUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CrewRepositoryInterface $crewRepository,
    ) {
    }

    /**
     * @param array $identity Auth identity: email_address, is_member, membership_number
     * @return bool True when anything changed
     */
    public function execute(User $user, array $identity): bool
    {
        $changed = false;

        $email = strtolower((string)($identity['email_address'] ?? ''));
        if ($email !== '' && strtolower($user->getEmail()) !== $email) {
            $other = $this->userRepository->findByEmail($email);
            if ($other === null || $other->getId() === $user->getId()) {
                $user->setEmail($email);
                $this->userRepository->save($user);
                $changed = true;
            }
        }

        if ($user->getAccountType() === 'crew') {
            $crew = $this->crewRepository->findByUserId($user->getId());
            if ($crew !== null) {
                $changed = $this->syncMembership($crew, $identity) || $changed;
            }
        }

        return $changed;
    }

    private function syncMembership(Crew $crew, array $identity): bool
    {
        $expected = !empty($identity['is_member']) ? ($identity['membership_number'] ?? null) : null;
        if ($expected === '') {
            $expected = null;
        }
        if ($crew->getMembershipNumber() === $expected) {
            return false;
        }

        // Preserve pipeline-managed rank dimensions; only the membership dimension follows the number.
        $rank = $crew->getRank();
        $crew->setMembershipNumber($expected);
        $crew->setRank($rank);
        $crew->setRankDimension(CrewRankDimension::MEMBERSHIP, Crew::calculateMembershipRank($expected));
        $this->crewRepository->save($crew);

        return true;
    }
}
