<?php

declare(strict_types=1);

namespace App\Application\UseCase\User;

use App\Application\DTO\Response\BoatResponse;
use App\Application\DTO\Response\CrewResponse;
use App\Application\DTO\Response\ProfileResponse;
use App\Application\DTO\Response\UserResponse;
use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;

/**
 * Get User Profile Use Case
 *
 * Retrieves complete user profile including user account, crew profile (if any), and boat profile (if any).
 */
class GetUserProfileUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private CrewRepositoryInterface $crewRepository,
        private BoatRepositoryInterface $boatRepository,
    ) {
    }

    /**
     * Execute get user profile
     *
     * @param int $userId User ID
     * @return ProfileResponse Complete profile with user, crew, and boat data
     * @throws \RuntimeException If user not found
     */
    public function execute(int $userId): ProfileResponse
    {
        // Get user
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found');
        }

        // Get crew profile if exists
        $crew = $this->crewRepository->findByUserId($userId);
        $crewResponse = $crew !== null ? CrewResponse::fromEntity($crew) : null;

        // Get boat profile if exists
        $boat = $this->boatRepository->findByOwnerUserId($userId);
        $boatResponse = $boat !== null ? BoatResponse::fromEntity($boat) : null;

        // Create profile response
        return new ProfileResponse(
            user: UserResponse::fromEntity($user),
            crewProfile: $crewResponse,
            boatProfile: $boatResponse,
        );
    }
}
