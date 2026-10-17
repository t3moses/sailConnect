<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\DTO\Request\AddProfileRequest;
use App\Application\DTO\Request\UpdateProfileRequest;
use App\Application\UseCase\User\AddProfileUseCase;
use App\Application\UseCase\User\GetUserProfileUseCase;
use App\Application\UseCase\User\UpdateUserProfileUseCase;
use App\Presentation\Response\JsonResponse;

/**
 * User Profile Controller
 *
 * Handles user profile management endpoints:
 * - GET /api/users/me - Get current user profile
 * - POST /api/users/me - Add crew or boat profile
 * - PATCH /api/users/me - Update user profile
 */
class UserController
{
    public function __construct(
        private GetUserProfileUseCase $getUserProfileUseCase,
        private AddProfileUseCase $addProfileUseCase,
        private UpdateUserProfileUseCase $updateUserProfileUseCase,
    ) {
    }

    /**
     * Get current user profile
     *
     * GET /api/users/me
     *
     * @param array $auth Authentication context from JWT middleware
     * @return JsonResponse
     */
    public function getProfile(array $auth): JsonResponse
    {
        $response = $this->getUserProfileUseCase->execute($auth['user_id']);

        return JsonResponse::success($response->toArray());
    }

    /**
     * Add crew or boat profile to existing account
     *
     * POST /api/users/me
     *
     * @param array $body Request body
     * @param array $auth Authentication context from JWT middleware
     * @return JsonResponse
     */
    public function addProfile(array $body, array $auth): JsonResponse
    {
        $request = AddProfileRequest::fromArray($body);
        $response = $this->addProfileUseCase->execute($auth['user_id'], $request);

        return JsonResponse::success([
            'message' => 'Profile added successfully',
            'profile' => $response->toArray(),
        ], 201);
    }

    /**
     * Update user profile
     *
     * PATCH /api/users/me
     *
     * @param array $body Request body
     * @param array $auth Authentication context from JWT middleware
     * @return JsonResponse
     */
    public function updateProfile(array $body, array $auth): JsonResponse
    {
        $request = UpdateProfileRequest::fromArray($body);
        $response = $this->updateUserProfileUseCase->execute($auth['user_id'], $request);

        return JsonResponse::success([
            'message' => 'Profile updated successfully',
            'profile' => $response->toArray(),
        ]);
    }
}
