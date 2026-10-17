<?php

declare(strict_types=1);

namespace App\Application\Port\Repository;

use App\Domain\Entity\User;

/**
 * User Repository Interface
 *
 * Defines the contract for user persistence operations.
 * Follows the Repository pattern with the interface in the Application layer
 * and implementation in the Infrastructure layer.
 */
interface UserRepositoryInterface
{
    /**
     * Find user by ID
     *
     * @param int $id User ID
     * @return User|null User entity or null if not found
     */
    public function findById(int $id): ?User;

    /**
     * Find user by email address
     *
     * @param string $email Email address
     * @return User|null User entity or null if not found
     */
    public function findByEmail(string $email): ?User;

    /**
     * Check if email already exists
     *
     * @param string $email Email address to check
     * @return bool True if email exists, false otherwise
     */
    public function emailExists(string $email): bool;

    /**
     * Save user (create or update)
     *
     * @param User $user User entity to save
     * @return void
     */
    public function save(User $user): void;

    /**
     * Delete user by ID
     *
     * @param int $id User ID to delete
     * @return void
     */
    public function delete(int $id): void;

    /**
     * Get total user count
     *
     * @return int Number of users
     */
    public function count(): int;

    /**
     * Find all users ordered by email
     *
     * @return User[] Array of all user entities
     */
    public function findAll(): array;
}
