<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Unit tests for IntegrationTestCase base class
 *
 * Verifies that:
 * - Phinx migrations run successfully
 * - Database schema is complete with all migrations
 * - Season config is initialized
 * - PDO connection is properly configured
 */
class IntegrationTestCaseTest extends IntegrationTestCase
{
    public function testPdoIsConfigured(): void
    {
        $this->assertNotNull($this->pdo);
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $this->pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertEquals(PDO::FETCH_ASSOC, $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testForeignKeysEnabled(): void
    {
        $result = $this->pdo->query("PRAGMA foreign_keys")->fetch();
        $this->assertEquals('1', $result['foreign_keys'], 'Foreign keys should be enabled');
    }

    public function testAllMigrationsRan(): void
    {
        // Verify phinxlog table exists
        $result = $this->pdo->query("
            SELECT name FROM sqlite_master WHERE type='table' AND name='phinxlog'
        ")->fetchAll();
        $this->assertNotEmpty($result, 'phinxlog table should exist after running migrations');

        // Verify all 17 migrations executed
        $versions = $this->pdo->query("
            SELECT version FROM phinxlog ORDER BY version
        ")->fetchAll(PDO::FETCH_COLUMN);

        $expected = [
            20260101000000,  // initial_schema
            20260130000000,  // add_users_authentication
            20260131000000,  // add_last_logout_column
            20260201000000,  // make_display_name_nullable
            20260221000000,  // remove_crew_rank_flexibility
            20260224000000,  // remove_email_columns
            20260227000000,  // add_cron_notifications
            20260302000000,  // create_locks_table
            20260316000000,  // create_password_reset_tokens_table
            20260703000000,  // rename_crew_commitment_rank
            20260705000000,  // add_rank_availability_constraints
            20260803132229,  // rename_crew_and_availability_rank_columns
            20260803134759,  // rename_boat_rank_columns
            20260825000000,  // add_active_to_crews
            20260826231116,  // create_no_shows_table
            20260828103827,  // add_initial_commitment_rank_to_crews
            20261020000000,  // link_users_to_auth
            20261021120000,  // move_identity_to_auth
            20261021140000,  // rename_users_is_admin
            20261021150000,  // drop_users_last_login_logout
            20261021160000,  // use_auth_user_id_as_user_key
        ];

        $this->assertEquals($expected, $versions, 'All 21 migrations should be applied');
    }

    public function testSeasonConfigInitialized(): void
    {
        $result = $this->pdo->query("SELECT * FROM season_config WHERE id = 1")->fetch();

        $this->assertNotEmpty($result, 'Season config should be initialized');
        $this->assertEquals(2026, $result['year']);
        $this->assertEquals('simulated', $result['source']);
        $this->assertEquals('2026-05-01 09:00:00', $result['simulated_date']);
        $this->assertEquals('12:45:00', $result['start_time']);
        $this->assertEquals('17:00:00', $result['finish_time']);
        $this->assertEquals('10:00:00', $result['blackout_from']);
        $this->assertEquals('18:00:00', $result['blackout_to']);
    }

    public function testLoginTimestampColumnsAreGone(): void
    {
        // Sign-in and sign-out belong to sailConnect
        $columns = array_column($this->pdo->query("PRAGMA table_info(users)")->fetchAll(), 'name');

        $this->assertNotContains('last_login', $columns);
        $this->assertNotContains('last_logout', $columns);
    }

    public function testCrewsDisplayNameNullable(): void
    {
        // Verify migration 20260201 ran successfully (nullable constraint)
        // Insert a crew without display_name to test nullability
        $stmt = $this->pdo->prepare("
            INSERT INTO crews (key)
            VALUES (?)
        ");
        $stmt->execute(['test_crew']);

        $result = $this->pdo->query("
            SELECT display_name FROM crews WHERE key = 'test_crew'
        ")->fetch();

        $this->assertNull(
            $result['display_name'],
            'display_name should be nullable after migration 20260201'
        );
    }

    public function testCoreTablesExist(): void
    {
        $expectedTables = [
            'boats',
            'crews',
            'events',
            'boat_availability',
            'crew_availability',
            'boat_history',
            'crew_history',
            'crew_whitelist',
            'season_config',
            'flotillas',
            'users',
            'locks',
            'phinxlog'
        ];

        $result = $this->pdo->query("
            SELECT name FROM sqlite_master WHERE type='table' ORDER BY name
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedTables as $table) {
            $this->assertContains($table, $result, "Table '{$table}' should exist");
        }
    }

    public function testCreateTestEventUtility(): void
    {
        $this->createTestEvent('Fri May 29', '2026-05-29');

        $result = $this->pdo->query("
            SELECT * FROM events WHERE event_id = 'Fri May 29'
        ")->fetch();

        $this->assertNotEmpty($result);
        $this->assertEquals('2026-05-29', $result['event_date']);
        $this->assertEquals('upcoming', $result['status']);
    }

    public function testCreateTestUserUtility(): void
    {
        $userId = $this->createTestUser('test@example.com', 'crew', false);

        $result = $this->pdo->query("
            SELECT * FROM users WHERE auth_user_id = '{$userId}'
        ")->fetch();

        $this->assertNotEmpty($result);
        $this->assertNotEmpty($result['auth_user_id']);
        $this->assertEquals('crew', $result['account_type']);
        $this->assertEquals(0, $result['is_program_admin']);
    }

    public function testCreateTestUserWithAdminFlag(): void
    {
        $userId = $this->createTestUser('admin@example.com', 'crew', true);

        $result = $this->pdo->query("
            SELECT is_program_admin FROM users WHERE auth_user_id = '{$userId}'
        ")->fetch();

        $this->assertEquals(1, $result['is_program_admin']);
    }

    public function testCreateTestUserReadsEmailFromAuthDatabase(): void
    {
        $userId = $this->createTestUser('test@example.com');

        $result = $this->pdo->query("
            SELECT email FROM users_full WHERE auth_user_id = '{$userId}'
        ")->fetch();

        $this->assertEquals('test@example.com', $result['email']);
    }

    public function testSailFridayDoesNotStoreIdentityData(): void
    {
        $columns = fn(string $table) => array_column(
            $this->pdo->query("PRAGMA main.table_info({$table})")->fetchAll(PDO::FETCH_ASSOC),
            'name'
        );

        $this->assertEmpty(array_intersect(['email', 'password_hash'], $columns('users')));
        $this->assertEmpty(array_intersect(['first_name', 'last_name', 'mobile', 'membership_number'], $columns('crews')));
        $this->assertEmpty(array_intersect(['owner_first_name', 'owner_last_name', 'owner_mobile'], $columns('boats')));
    }
}
