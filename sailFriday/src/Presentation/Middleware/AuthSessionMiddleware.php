<?php

declare(strict_types=1);

namespace App\Presentation\Middleware;

use App\Application\Port\Repository\UserRepositoryInterface;
use App\Presentation\Response\JsonResponse;
use SailConnect\Auth\Client\SessionVerifier;

/**
 * Auth Session Middleware
 *
 * Authenticates requests with the shared sailConnect single-sign-on cookie (validated by the
 * auth service's SessionVerifier) and maps the identity to this application's local user.
 */
class AuthSessionMiddleware
{
    private const FAIL_UNAUTHENTICATED = 'unauthenticated';
    private const FAIL_NOT_ENROLLED = 'not_enrolled';

    private string $failure = self::FAIL_UNAUTHENTICATED;

    public function __construct(
        private SessionVerifier $verifier,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * The signed-in sailConnect identity, whether or not it has a sailFriday account yet.
     *
     * @return array|null Auth user row (user_id, email_address, first_name, ...) or null
     */
    public function identity(): ?array
    {
        return $this->verifier->currentUser();
    }

    /**
     * Authenticate and resolve the local sailFriday user.
     *
     * @return array|null ['user_id' => string, 'email' => string, 'account_type' => string, 'is_admin' => bool,
     *                     'identity' => array] or null (see authenticationFailed())
     */
    public function authenticate(): ?array
    {
        $identity = $this->identity();
        if ($identity === null) {
            $this->failure = self::FAIL_UNAUTHENTICATED;
            return null;
        }

        $user = $this->userRepository->findById($identity['user_id']);
        if ($user === null) {
            $this->failure = self::FAIL_NOT_ENROLLED;
            return null;
        }

        return [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'account_type' => $user->getAccountType(),
            'is_admin' => $user->isAdmin(),
            'identity' => $identity,
        ];
    }

    /**
     * Response for a failed authenticate() / identity() call.
     */
    public function authenticationFailed(): JsonResponse
    {
        if ($this->failure === self::FAIL_NOT_ENROLLED) {
            return JsonResponse::error('Complete your Sail Friday registration to continue.', 403, [
                'code' => 'enrollment_required',
            ]);
        }

        return JsonResponse::error('Authentication required. Please sign in.', 401, [
            'code' => 'authentication_required',
        ]);
    }
}
