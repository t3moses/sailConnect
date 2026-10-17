<?php

declare(strict_types=1);

namespace App\Application\Port\Service;

/**
 * Token Service Interface
 *
 * Defines the contract for JWT token generation and validation.
 * Follows the Dependency Inversion Principle with the interface in the
 * Application layer and implementation in the Infrastructure layer.
 */
interface TokenServiceInterface
{
    /**
     * Generate JWT token for user
     *
     * @param int $userId User ID
     * @param string $email User email
     * @param string $accountType Account type ('crew' or 'boat_owner')
     * @param bool $isAdmin Admin status
     * @return string JWT token
     */
    public function generate(int $userId, string $email, string $accountType, bool $isAdmin): string;

    /**
     * Validate and decode JWT token
     *
     * @param string $token JWT token to validate
     * @return array|null Decoded payload or null if invalid/expired
     *                    Payload structure: ['sub' => userId, 'email' => email, 'account_type' => type, 'is_admin' => bool, 'iat' => timestamp, 'exp' => timestamp]
     */
    public function validate(string $token): ?array;

    /**
     * Get token expiration time in minutes
     *
     * @return int Expiration time in minutes
     */
    public function getExpirationMinutes(): int;
}
