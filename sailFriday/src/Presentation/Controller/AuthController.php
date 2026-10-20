<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\DTO\Request\RegisterRequest;
use App\Application\UseCase\Auth\GetSessionUseCase;
use App\Application\UseCase\Auth\RegisterUseCase;
use App\Presentation\Response\JsonResponse;

/**
 * Authentication Controller
 *
 * Sign-in, sign-out, password reset and credentials are handled by the sailConnect auth service.
 * This controller only covers the Sail Friday side of a signed-in sailConnect user:
 * - POST /api/auth/register - Enroll the signed-in sailConnect user as crew or boat owner
 * - GET /api/auth/identity - Get the signed-in sailConnect identity
 * - GET /api/auth/session - Get current session info
 */
class AuthController
{
    public function __construct(
        private RegisterUseCase $registerUseCase,
        private GetSessionUseCase $getSessionUseCase,
    ) {
    }

    /**
     * Enroll the signed-in sailConnect user in Sail Friday
     *
     * POST /api/auth/register
     *
     * @param array $body Request body: accountType, profile
     * @param array $auth sailConnect identity (route auth mode "identity")
     * @return JsonResponse
     */
    public function register(array $body, array $auth): JsonResponse
    {
        $request = RegisterRequest::fromArray($body);
        $user = $this->registerUseCase->execute($request, $auth);

        return JsonResponse::success([
            'user' => $user->toArray(),
            'message' => 'Registration successful',
        ], 201);
    }

    /**
     * Get the signed-in sailConnect identity (available before enrollment)
     *
     * GET /api/auth/identity
     *
     * @param array $auth sailConnect identity (route auth mode "identity")
     * @return JsonResponse
     */
    public function getIdentity(array $auth): JsonResponse
    {
        return JsonResponse::success([
            'identity' => [
                'email' => $auth['email_address'],
                'firstName' => $auth['first_name'],
                'lastName' => $auth['last_name'],
                'displayName' => $auth['display_name'],
                'isMember' => (bool)$auth['is_member'],
                'membershipNumber' => $auth['membership_number'],
            ],
        ]);
    }

    /**
     * Get current session information
     *
     * GET /api/auth/session
     *
     * @param array $auth Authentication context from the session middleware
     * @return JsonResponse
     */
    public function getSession(array $auth): JsonResponse
    {
        $response = $this->getSessionUseCase->execute($auth['user_id']);

        return JsonResponse::success([
            'user' => $response->toArray(),
        ]);
    }
}
