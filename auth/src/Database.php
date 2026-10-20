<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use PDO;

final class Database
{
    public static function connect(string $path, bool $readOnly = false): PDO
    {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if (!$readOnly) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        return $pdo;
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function ts(string $utc): int
    {
        return (int) strtotime($utc . ' UTC');
    }
}
