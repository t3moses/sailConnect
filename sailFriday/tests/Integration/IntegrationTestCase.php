<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Persistence\SQLite\AuthDatabase;
use App\Infrastructure\Persistence\SQLite\Connection;
use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Base class for integration tests requiring database access
 *
 * Provides:
 * - Phinx migration execution for in-memory SQLite
 * - Connection singleton management
 * - Common test data utilities
 *
 * Usage:
 * ```php
 * class MyIntegrationTest extends IntegrationTestCase
 * {
 *     protected function setUp(): void
 *     {
 *         parent::setUp();  // Runs all Phinx migrations + initializes season config
 *
 *         // Your test-specific setup
 *         $this->myRepository = new MyRepository();
 *     }
 * }
 * ```
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create in-memory database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable foreign keys (required for schema integrity)
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Run all Phinx migrations
        $this->runMigrations();

        // Identity (email, names, mobile, membership) is read from the sailConnect auth database
        $this->attachAuthDatabase();

        // Initialize season config (most tests need this)
        $this->initializeSeasonConfig();

        // Set test connection globally
        Connection::setTestConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Connection::resetTestConnection();
        parent::tearDown();
    }

    /**
     * Attach an in-memory copy of the auth service's users table as schema "auth" and create the identity views
     */
    protected function attachAuthDatabase(): void
    {
        AuthDatabase::attach($this->pdo, ':memory:');

        $schema = (string)file_get_contents(dirname(__DIR__, 3) . '/auth/database/schema.sql');
        if (!preg_match('/CREATE TABLE users \(.*?\n\s*\);/s', $schema, $m)) {
            throw new \RuntimeException('auth users table not found in auth/database/schema.sql');
        }
        $this->pdo->exec(preg_replace('/^CREATE TABLE users/', 'CREATE TABLE auth.users', $m[0]));
    }

    /**
     * Run Phinx migrations programmatically on in-memory database
     *
     * This method:
     * 1. Configures Phinx to use the existing in-memory PDO connection
     * 2. Points to the database/migrations directory
     * 3. Runs all pending migrations in the 'testing' environment
     * 4. Creates a phinxlog table to track applied migrations
     */
    protected function runMigrations(): void
    {
        $projectRoot = dirname(__DIR__, 2); // From tests/Integration to project root

        $config = new Config([
            'paths' => [
                'migrations' => $projectRoot . '/database/migrations'
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_environment' => 'testing',
                'testing' => [
                    'adapter' => 'sqlite',
                    'connection' => $this->pdo,  // Inject existing PDO
                    'name' => ':memory:'          // Required by Phinx config
                ]
            ],
            'version_order' => 'creation'
        ]);

        $input = new StringInput('migrate -e testing');
        $output = new NullOutput();
        $manager = new Manager($config, $input, $output);
        $manager->migrate('testing');
    }

    /**
     * Initialize season config with test data
     *
     * Inserts a default season configuration with simulated date mode
     * for consistent test behavior.
     */
    protected function initializeSeasonConfig(): void
    {
        $this->pdo->exec("
            INSERT OR REPLACE INTO season_config (
                id, year, source, simulated_date,
                start_time, finish_time, blackout_from, blackout_to
            ) VALUES (
                1, 2026, 'simulated', '2026-05-01 09:00:00',
                '12:45:00', '17:00:00', '10:00:00', '18:00:00'
            )
        ");
    }

    // ==================== TEST UTILITIES ====================

    /**
     * Create test event
     *
     * @param string $eventId Event identifier (e.g., 'Fri May 29')
     * @param string $date Event date (YYYY-MM-DD format)
     * @return void
     */
    protected function createTestEvent(string $eventId, string $date): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO events (event_id, event_date, start_time, finish_time, status)
            VALUES (?, ?, '12:45:00', '17:00:00', 'upcoming')
        ");
        $stmt->execute([$eventId, $date]);
    }

    /**
     * Create a sailConnect identity (a row in the attached auth database)
     *
     * @param array $overrides auth.users columns to set (first_name, last_name, email_address, mobile_number,
     *                         is_member, membership_number, ...)
     * @return string The auth user_id
     */
    protected function createIdentity(array $overrides = []): string
    {
        static $sequence = 0;
        $sequence++;

        $row = $overrides + [
            'user_id' => sprintf('auth%012d', $sequence),
            'email_address' => "identity{$sequence}@example.com",
            'password_hash' => '',
            'first_name' => 'Test',
            'last_name' => 'Person',
            'display_name' => "TestP{$sequence}",
            'is_member' => 0,
            'membership_number' => null,
            'mobile_number' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
        // A membership number only counts for members, as in the auth service
        if (!array_key_exists('is_member', $overrides)) {
            $row['is_member'] = ($row['membership_number'] ?? '') !== '' ? 1 : 0;
        }
        if (!$row['is_member']) {
            $row['membership_number'] = null;
        }

        $columns = array_keys($row);
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth.users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $stmt->execute(array_values($row));

        return $row['user_id'];
    }

    /**
     * Change the sailConnect identity behind a Sail Friday user
     *
     * @param string $userId User ID (the sailConnect user ID)
     * @param array $fields auth.users columns (first_name, last_name, mobile_number, membership_number, ...)
     */
    protected function updateIdentity(string $userId, array $fields): void
    {
        if (array_key_exists('membership_number', $fields) && !array_key_exists('is_member', $fields)) {
            $fields['is_member'] = ($fields['membership_number'] ?? '') !== '' ? 1 : 0;
            $fields['membership_number'] = $fields['is_member'] ? $fields['membership_number'] : null;
        }
        if ($fields === []) {
            return;
        }

        $assignments = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($fields)));
        $stmt = $this->pdo->prepare(
            "UPDATE auth.users SET {$assignments} WHERE user_id = ?"
        );
        $stmt->execute([...array_values($fields), $userId]);
    }

    /**
     * Give a crew or boat row its sailConnect identity (names, mobile, membership)
     *
     * Updates the identity of the row's user, or creates and links a user when the row has none.
     *
     * @param string $table 'crews' or 'boats'
     * @param int $rowId crews.id / boats.id
     * @param array $identity auth.users columns (first_name, last_name, mobile_number, membership_number)
     */
    protected function linkIdentity(string $table, int $rowId, array $identity): void
    {
        [$userColumn, $accountType] = $table === 'crews' ? ['user_id', 'crew'] : ['owner_user_id', 'boat_owner'];

        $stmt = $this->pdo->prepare("SELECT {$userColumn} FROM {$table} WHERE id = ?");
        $stmt->execute([$rowId]);
        $userId = $stmt->fetchColumn();

        if ($userId === null || $userId === false) {
            $userId = $this->createLinkedUser($identity, $accountType);
            $this->pdo->prepare("UPDATE {$table} SET {$userColumn} = ? WHERE id = ?")->execute([$userId, $rowId]);
        } else {
            $this->updateIdentity((string)$userId, $identity);
        }

        // Creating the user moved lastInsertId(); callers still expect the crew/boat row they just inserted
        $this->pdo->exec('CREATE TEMP TABLE IF NOT EXISTS last_insert_restore (id INTEGER PRIMARY KEY)');
        $this->pdo->prepare('INSERT OR REPLACE INTO last_insert_restore (id) VALUES (?)')->execute([$rowId]);
    }

    /**
     * Create a Sail Friday user linked to a new sailConnect identity
     *
     * @param array $identity auth.users columns (first_name, last_name, mobile_number, membership_number, ...)
     * @return string User ID (the sailConnect user ID)
     */
    protected function createLinkedUser(
        array $identity = [],
        string $accountType = 'crew',
        bool $isAdmin = false
    ): string {
        $authUserId = $this->createIdentity($identity);

        $stmt = $this->pdo->prepare('
            INSERT INTO users (auth_user_id, account_type, is_program_admin, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([$authUserId, $accountType, $isAdmin ? 1 : 0]);

        return $authUserId;
    }

    /**
     * Build an unsaved Sail Friday user entity linked to a new sailConnect identity with this email
     */
    protected function newUser(string $email, string $accountType = 'crew', bool $isAdmin = false): \App\Domain\Entity\User
    {
        return new \App\Domain\Entity\User(
            $email,
            $accountType,
            $isAdmin,
            id: $this->createIdentity(['email_address' => $email])
        );
    }

    /**
     * Create test user (for authentication tests)
     *
     * @param string $email User email address
     * @param string $accountType Account type ('crew' or 'boat_owner')
     * @param bool $isAdmin Whether user has admin privileges
     * @return string User ID
     */
    protected function createTestUser(
        string $email = 'test@example.com',
        string $accountType = 'crew',
        bool $isAdmin = false
    ): string {
        return $this->createLinkedUser(['email_address' => $email], $accountType, $isAdmin);
    }
}
