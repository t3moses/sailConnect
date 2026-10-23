<?php

declare(strict_types=1);

namespace MatchRace;

use PDO;
use RuntimeException;

/**
 * matchRace.db access. Identity (email, names) belongs to the auth service, so the auth database is
 * attached read-only-by-convention as schema "auth" and exposed through the temporary view users_full.
 */
final class Database
{
    public static function connect(string $dbPath, string $authDbPath): PDO
    {
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec((string) file_get_contents(__DIR__ . '/../database/schema.sql'));

        if (!is_file($authDbPath)) {
            // ATTACH silently creates a missing file, which would hide a misconfigured path.
            throw new RuntimeException("sailConnect auth database not found at {$authDbPath}");
        }
        $pdo->exec('ATTACH DATABASE ' . $pdo->quote($authDbPath) . ' AS auth');
        $pdo->exec("
            CREATE TEMP VIEW users_full AS
            SELECT u.*, a.email_address AS email, a.first_name, a.last_name
            FROM main.users u
            JOIN auth.users a ON a.user_id = u.auth_user_id
        ");

        return $pdo;
    }
}
