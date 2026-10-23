<?php

declare(strict_types=1);

namespace MatchRace;

use PDO;

final class UserRepository
{
    public const TYPES = [
        'boat_owner' => 'Boat Owner',
        'crew' => 'Crew Member',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(string $authUserId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM users_full WHERE auth_user_id = ?');
        $st->execute([$authUserId]);

        return $st->fetch() ?: null;
    }

    public function create(string $authUserId, string $userType): void
    {
        $st = $this->pdo->prepare('INSERT OR IGNORE INTO users (auth_user_id, user_type) VALUES (?, ?)');
        $st->execute([$authUserId, $userType]);
    }

    /** @return list<array{first_name:string,last_name:string}> */
    public function namesByType(string $userType): array
    {
        $st = $this->pdo->prepare('SELECT first_name, last_name FROM users_full WHERE user_type = ? ORDER BY first_name, last_name');
        $st->execute([$userType]);

        return $st->fetchAll();
    }
}
