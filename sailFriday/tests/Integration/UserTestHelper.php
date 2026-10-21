<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\SQLite\Connection;
use PDO;

/**
 * User Test Helper
 *
 * Provides centralized user deletion for API tests (users are created through the sailConnect auth
 * service and the register endpoint; see ApiTestTrait). Deleting a test user also removes its sailConnect
 * identity from the attached auth database.
 */
class UserTestHelper
{
    private static ?PDO $pdo = null;

    /**
     * Get database connection
     */
    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = Connection::getInstance();
        }
        return self::$pdo;
    }

    /**
     * Delete test user and associated profiles
     *
     * @param string $userId User ID to delete
     */
    public static function deleteTestUser(string $userId): void
    {
        $pdo = self::getPdo();

        // Delete crew profile if exists
        $stmt = $pdo->prepare('DELETE FROM crews WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Clean up crew_whitelist entries referencing this user's boats
        $stmt = $pdo->prepare('
            DELETE FROM crew_whitelist
            WHERE boat_key IN (SELECT key FROM boats WHERE owner_user_id = :user_id)
        ');
        $stmt->execute(['user_id' => $userId]);

        // Delete boat profile if exists
        $stmt = $pdo->prepare('DELETE FROM boats WHERE owner_user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Delete user, then the sailConnect identity the test created for it
        $stmt = $pdo->prepare('DELETE FROM users WHERE auth_user_id = :id');
        $stmt->execute(['id' => $userId]);

        $stmt = $pdo->prepare('DELETE FROM auth.users WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
    }
}
