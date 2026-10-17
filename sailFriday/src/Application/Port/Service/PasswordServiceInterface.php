<?php

declare(strict_types=1);

namespace App\Application\Port\Service;

/**
 * Password Service Interface
 *
 * Defines the contract for password hashing, verification, and validation.
 * Follows the Dependency Inversion Principle with the interface in the
 * Application layer and implementation in the Infrastructure layer.
 */
interface PasswordServiceInterface
{
    /**
     * Hash a plaintext password
     *
     * @param string $password Plaintext password
     * @return string Hashed password
     */
    public function hash(string $password): string;

    /**
     * Verify a password against a hash
     *
     * @param string $password Plaintext password to verify
     * @param string $hash Hashed password to verify against
     * @return bool True if password matches hash, false otherwise
     */
    public function verify(string $password, string $hash): bool;

    /**
     * Check if password meets security requirements
     *
     * @param string $password Password to validate
     * @return bool True if password meets requirements, false otherwise
     */
    public function meetsRequirements(string $password): bool;

    /**
     * Get password requirements message
     *
     * @return string Human-readable requirements description
     */
    public function getRequirementsMessage(): string;
}
