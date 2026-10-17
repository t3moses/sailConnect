<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Application\DTO\Response\UserResponse;
use App\Application\Port\Repository\UserRepositoryInterface;

/**
 * Get Session Use Case
 *
 * Retrieves current user information from validated JWT token.
 */
class GetSessionUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Execute get session
     *
     * @param int $userId User ID from JWT token
     * @return UserResponse User information
     * @throws \RuntimeException If user not found
     */
    public function execute(int $userId): UserResponse
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new \RuntimeException('User not found');
        }

        return UserResponse::fromEntity($user);
    }
}
