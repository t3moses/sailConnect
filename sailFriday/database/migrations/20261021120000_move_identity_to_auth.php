<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\SQLite\AuthDatabase;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

/**
 * Removes identity data that now lives only in the sailConnect auth database.
 *
 * Dropped: users.email, users.password_hash, crews.first_name / last_name / mobile / membership_number,
 * boats.owner_first_name / owner_last_name / owner_mobile and the unused password_reset_tokens table.
 * Sail Friday reads these through users.auth_user_id (see AuthDatabase).
 *
 * Auth had no mobile number, so mobile numbers are copied into auth.users.mobile_number first (never
 * overwriting a number already there) and the copy is verified before anything is dropped.
 *
 * Requirements, checked below: every user is linked (bin/migrate-users-to-auth.php --apply has been run) and
 * the auth database has been migrated to include users.mobile_number. Back up both databases first.
 */
final class MoveIdentityToAuth extends AbstractMigration
{
    public function up(): void
    {
        $unlinked = (int)$this->fetchRow('SELECT COUNT(*) AS n FROM users WHERE auth_user_id IS NULL')['n'];
        if ($unlinked > 0) {
            throw new RuntimeException(
                "{$unlinked} user(s) are not linked to a sailConnect account. "
                . 'Run bin/migrate-users-to-auth.php --apply first; their names and email are dropped by this migration.'
            );
        }

        $mobiles = $this->fetchAll("
            SELECT u.auth_user_id AS auth_user_id,
                   COALESCE(NULLIF(TRIM(c.mobile), ''), NULLIF(TRIM(b.owner_mobile), '')) AS mobile
            FROM users u
            LEFT JOIN crews c ON c.user_id = u.id
            LEFT JOIN boats b ON b.owner_user_id = u.id
            WHERE COALESCE(NULLIF(TRIM(c.mobile), ''), NULLIF(TRIM(b.owner_mobile), '')) IS NOT NULL
        ");
        if ($mobiles) {
            $this->copyMobilesToAuth($mobiles);
        }

        foreach (['idx_users_email', 'idx_crews_name', 'idx_boats_owner'] as $index) {
            $this->execute("DROP INDEX IF EXISTS {$index}");
        }
        $this->execute('DROP TABLE IF EXISTS password_reset_tokens');

        foreach (['email', 'password_hash'] as $column) {
            $this->execute("ALTER TABLE users DROP COLUMN {$column}");
        }
        foreach (['first_name', 'last_name', 'mobile', 'membership_number'] as $column) {
            $this->execute("ALTER TABLE crews DROP COLUMN {$column}");
        }
        foreach (['owner_first_name', 'owner_last_name', 'owner_mobile'] as $column) {
            $this->execute("ALTER TABLE boats DROP COLUMN {$column}");
        }
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'Identity data now lives in the auth database. Restore the sailFriday.db backup taken before this migration.'
        );
    }

    /**
     * @param list<array{auth_user_id: string, mobile: string}> $mobiles
     */
    private function copyMobilesToAuth(array $mobiles): void
    {
        $authPath = AuthDatabase::path();
        if (!is_file($authPath)) {
            throw new RuntimeException("sailConnect auth database not found at {$authPath} (set AUTH_DB_PATH or AUTH_DIR).");
        }

        $auth = new PDO('sqlite:' . $authPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $auth->exec('PRAGMA busy_timeout = 5000');
        $columns = array_column($auth->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('mobile_number', $columns, true)) {
            throw new RuntimeException('The auth database has no users.mobile_number column. Run vendor/bin/phinx migrate in auth first.');
        }

        $auth->beginTransaction();
        $set = $auth->prepare("
            UPDATE users SET mobile_number = ?
            WHERE user_id = ? AND (mobile_number IS NULL OR mobile_number = '')
        ");
        foreach ($mobiles as $row) {
            $set->execute([trim($row['mobile']), $row['auth_user_id']]);
        }
        $auth->commit();

        $has = $auth->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND mobile_number IS NOT NULL AND mobile_number <> ''");
        foreach ($mobiles as $row) {
            $has->execute([$row['auth_user_id']]);
            if ((int)$has->fetchColumn() !== 1) {
                throw new RuntimeException("Mobile number for auth user {$row['auth_user_id']} was not saved; nothing was dropped.");
            }
        }
    }
}
