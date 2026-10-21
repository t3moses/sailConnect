<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\SQLite;

use App\Application\Port\Repository\UserRepositoryInterface;
use App\Domain\Entity\User;
use PDO;

/**
 * User Repository
 *
 * SQLite implementation of UserRepositoryInterface.
 * Handles all user persistence operations using PDO prepared statements.
 */
class UserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    /**
     * Find user by ID
     *
     * @param string $id User ID (the sailConnect user ID)
     * @return User|null User entity or null if not found
     */
    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM users_full WHERE auth_user_id = :id LIMIT 1
        ');

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Find user by email address
     *
     * @param string $email Email address
     * @return User|null User entity or null if not found
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM users_full WHERE email = :email COLLATE NOCASE LIMIT 1
        ');

        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Check if email already exists
     *
     * @param string $email Email address to check
     * @return bool True if email exists, false otherwise
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM users_full WHERE email = :email COLLATE NOCASE
        ');

        $stmt->execute(['email' => $email]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Save user (create or update)
     *
     * @param User $user User entity to save
     * @return void
     */
    public function save(User $user): void
    {
        if ($user->getId() === null) {
            throw new \InvalidArgumentException('A user needs its sailConnect user ID before it can be saved');
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE auth_user_id = :id');
        $stmt->execute(['id' => $user->getId()]);

        if ((int)$stmt->fetchColumn() > 0) {
            $this->update($user);
        } else {
            $this->insert($user);
        }
    }

    /**
     * Delete user by ID
     *
     * @param string $id User ID to delete
     * @return void
     */
    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM users WHERE auth_user_id = :id
        ');

        $stmt->execute(['id' => $id]);
    }

    /**
     * Get total user count
     *
     * @return int Number of users
     */
    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users');
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find all users ordered by email
     *
     * @return User[] Array of all user entities
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM users_full ORDER BY email ASC');
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    /**
     * Insert new user
     *
     * @param User $user User entity to insert
     * @return void
     */
    private function insert(User $user): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (auth_user_id, account_type, is_program_admin, created_at, updated_at)
            VALUES (:id, :account_type, :is_program_admin, :created_at, :updated_at)
        ');

        $stmt->execute([
            'id' => $user->getId(),
            'account_type' => $user->getAccountType(),
            'is_program_admin' => $user->isAdmin() ? 1 : 0,
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update existing user
     *
     * @param User $user User entity to update
     * @return void
     */
    private function update(User $user): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE users
            SET account_type = :account_type,
                is_program_admin = :is_program_admin,
                updated_at = :updated_at
            WHERE auth_user_id = :id
        ');

        $stmt->execute([
            'id' => $user->getId(),
            'account_type' => $user->getAccountType(),
            'is_program_admin' => $user->isAdmin() ? 1 : 0,
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Hydrate database row into User entity
     *
     * @param array $row Database row
     * @return User User entity
     */
    private function hydrate(array $row): User
    {
        $user = new User(
            email: (string)$row['email'],
            accountType: $row['account_type'],
            isAdmin: (bool)$row['is_program_admin'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            id: (string)$row['auth_user_id'],
        );

        return $user;
    }
}
