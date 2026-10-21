<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\SQLite;

use PDO;
use RuntimeException;
use SailConnect\Auth\Config;

/**
 * sailConnect auth database access
 *
 * Email address, names, mobile number and membership belong to the auth service. Sail Friday reads
 * them by attaching the auth database to its own connection as schema "auth" and exposing them
 * through temporary views (users_full, crews_full, boats_full) that join on the sailConnect user ID (users.auth_user_id, crews.user_id, boats.owner_user_id).
 * Repositories read the views and write the base tables, so identity data is never stored here.
 */
final class AuthDatabase
{
    /**
     * Path of the auth database: AUTH_DB_PATH, else the DB_PATH configured in the auth service's own .env
     * (AUTH_DIR, default ../auth).
     */
    public static function path(): string
    {
        $override = self::setting('AUTH_DB_PATH');
        if ($override !== '') {
            return $override;
        }

        $authDir = self::setting('AUTH_DIR');
        if ($authDir === '') {
            $authDir = dirname(__DIR__, 5) . '/auth';
        }

        return Config::load($authDir)->dbPath();
    }

    /** A setting from the process environment or, for CLI scripts that only load .env into $_ENV, from there. */
    private static function setting(string $name): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : (string)($_ENV[$name] ?? '');
    }

    /**
     * Attach the auth database to $pdo as "auth" and create the identity views. Safe to repeat.
     *
     * Must run outside a transaction (SQLite cannot ATTACH inside one).
     *
     * @param string $authDbPath File path, or ':memory:' for tests
     */
    public static function attach(PDO $pdo, string $authDbPath): void
    {
        $attached = array_column($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('auth', $attached, true)) {
            // ATTACH silently creates a missing file, which would hide a misconfigured path.
            if ($authDbPath !== ':memory:' && !is_file($authDbPath)) {
                throw new RuntimeException("sailConnect auth database not found at {$authDbPath}");
            }
            $pdo->exec('ATTACH DATABASE ' . $pdo->quote($authDbPath) . ' AS auth');
        }

        $pdo->exec('DROP VIEW IF EXISTS temp.users_full');
        $pdo->exec('DROP VIEW IF EXISTS temp.crews_full');
        $pdo->exec('DROP VIEW IF EXISTS temp.boats_full');

        $pdo->exec("
            CREATE TEMP VIEW users_full AS
            SELECT u.*, a.email_address AS email
            FROM main.users u
            JOIN auth.users a ON a.user_id = u.auth_user_id
        ");
        $pdo->exec("
            CREATE TEMP VIEW crews_full AS
            SELECT c.*,
                   COALESCE(a.first_name, '') AS first_name,
                   COALESCE(a.last_name, '') AS last_name,
                   a.mobile_number AS mobile,
                   CASE WHEN a.is_member = 1 THEN a.membership_number END AS membership_number
            FROM main.crews c
            LEFT JOIN auth.users a ON a.user_id = c.user_id
        ");
        $pdo->exec("
            CREATE TEMP VIEW boats_full AS
            SELECT b.*,
                   COALESCE(a.first_name, '') AS owner_first_name,
                   COALESCE(a.last_name, '') AS owner_last_name,
                   a.mobile_number AS owner_mobile
            FROM main.boats b
            LEFT JOIN auth.users a ON a.user_id = b.owner_user_id
        ");
    }
}
