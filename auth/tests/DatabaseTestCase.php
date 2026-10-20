<?php

declare(strict_types=1);

namespace SailConnect\Auth\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use SailConnect\Auth\UserRepository;

/** Base class: a fresh in-memory database built from database/schema.sql. */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $db;
    protected UserRepository $users;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
        $this->users = new UserRepository($this->db);
    }

    /** @return array<string,mixed> */
    protected function makeUser(string $first = 'Tim', string $last = 'Moses', ?string $email = null, array $extra = []): array
    {
        return $this->users->create($extra + [
            'first_name' => $first,
            'last_name' => $last,
            'email_address' => $email ?? strtolower($first . '.' . $last . bin2hex(random_bytes(2))) . '@example.com',
            'password_hash' => password_hash('Passw0rdX', PASSWORD_BCRYPT, ['cost' => 4]),
            'is_member' => false,
        ]);
    }
}
