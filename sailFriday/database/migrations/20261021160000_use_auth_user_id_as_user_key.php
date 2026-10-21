<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

/**
 * Makes the sailConnect user ID the only user identifier.
 *
 * users.id (integer) is dropped and users.auth_user_id becomes the primary key. crews.user_id and
 * boats.owner_user_id change from the old integer to the sailConnect user ID (TEXT), so they join straight to
 * auth.users. SQLite cannot change a primary key in place, so users, crews and boats are rebuilt; the crew and
 * boat row IDs (referenced by availability, history, whitelist and no-shows) are preserved.
 *
 * Refuses to run while any user is not linked to a sailConnect account. Back up sailFriday.db first.
 */
final class UseAuthUserIdAsUserKey extends AbstractMigration
{
    public function up(): void
    {
        $unlinked = (int)$this->fetchRow("SELECT COUNT(*) AS n FROM users WHERE auth_user_id IS NULL OR auth_user_id = ''")['n'];
        if ($unlinked > 0) {
            throw new RuntimeException(
                "{$unlinked} user(s) are not linked to a sailConnect account; run bin/migrate-users-to-auth.php --apply first."
            );
        }

        // Dropping a parent table fires ON DELETE SET NULL on its children while foreign keys are enforced, which
        // would wipe crews.user_id / boats.owner_user_id. PRAGMA foreign_keys cannot change inside the transaction
        // Phinx opened, so step outside it for the rebuild.
        $pdo = $this->getAdapter()->getConnection();
        $foreignKeys = (bool)$this->fetchRow('PRAGMA foreign_keys')['foreign_keys'];
        if ($foreignKeys) {
            $pdo->commit();
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->beginTransaction();
        }

        try {
            $this->rebuild();
            $violations = $this->fetchAll('PRAGMA foreign_key_check');
            if ($violations) {
                throw new RuntimeException('Foreign key check failed after the rebuild: ' . json_encode($violations));
            }
        } catch (Throwable $e) {
            if ($foreignKeys) {
                $pdo->rollBack();
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->beginTransaction(); // Phinx expects to roll back its own transaction
            }
            throw $e;
        }

        if ($foreignKeys) {
            $pdo->commit();
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->beginTransaction(); // Phinx commits this one
        }
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException();
    }

    private function rebuild(): void
    {
        $sequences = [];
        foreach (['crews', 'boats'] as $table) {
            $row = $this->fetchRow("SELECT seq FROM sqlite_sequence WHERE name = '{$table}'");
            $sequences[$table] = $row ? (int)$row['seq'] : null;
        }

        $this->execute("
            CREATE TABLE users_new (
                `auth_user_id` TEXT NOT NULL PRIMARY KEY,
                `account_type` VARCHAR(50) NOT NULL,
                `is_program_admin` BOOLEAN_INTEGER NULL DEFAULT 0,
                `created_at` DATETIME_TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME_TEXT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->execute('
            INSERT INTO users_new (auth_user_id, account_type, is_program_admin, created_at, updated_at)
            SELECT auth_user_id, account_type, is_program_admin, created_at, updated_at FROM users
        ');

        $this->execute("
            CREATE TABLE crews_new (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                `key` VARCHAR(255) NOT NULL,
                `display_name` VARCHAR(255) NULL,
                `partner_key` VARCHAR(255) NULL,
                `social_preference` VARCHAR(3) NULL DEFAULT 'No',
                `skill` INTEGER NULL DEFAULT 0,
                `experience` TEXT NULL,
                `commitment_rank` INTEGER NULL DEFAULT 0,
                `membership_rank` INTEGER NULL DEFAULT 0,
                `absence_rank` INTEGER NULL DEFAULT 0,
                `created_at` DATETIME_TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME_TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                `user_id` TEXT NULL,
                `active` BOOLEAN_INTEGER NOT NULL DEFAULT 1,
                `initial_commitment_rank` INTEGER NOT NULL DEFAULT 2,
                FOREIGN KEY (`user_id`) REFERENCES `users` (`auth_user_id`) ON DELETE SET NULL ON UPDATE NO ACTION
            )
        ");
        $this->execute('
            INSERT INTO crews_new (id, key, display_name, partner_key, social_preference, skill, experience,
                commitment_rank, membership_rank, absence_rank, created_at, updated_at, user_id, active,
                initial_commitment_rank)
            SELECT c.id, c.key, c.display_name, c.partner_key, c.social_preference, c.skill, c.experience,
                c.commitment_rank, c.membership_rank, c.absence_rank, c.created_at, c.updated_at,
                (SELECT u.auth_user_id FROM users u WHERE u.id = c.user_id), c.active, c.initial_commitment_rank
            FROM crews c
        ');

        $this->execute("
            CREATE TABLE boats_new (
                `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                `key` VARCHAR(255) NOT NULL,
                `display_name` VARCHAR(255) NULL,
                `min_berths` INTEGER NOT NULL DEFAULT 1,
                `max_berths` INTEGER NOT NULL DEFAULT 1,
                `assistance_required` VARCHAR(3) NULL DEFAULT 'No',
                `social_preference` VARCHAR(3) NULL DEFAULT 'No',
                `flexibility_rank` INTEGER NULL DEFAULT 1,
                `absence_rank` INTEGER NULL DEFAULT 0,
                `created_at` DATETIME_TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME_TEXT NULL DEFAULT CURRENT_TIMESTAMP,
                `owner_user_id` TEXT NULL,
                FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`auth_user_id`) ON DELETE SET NULL ON UPDATE NO ACTION
            )
        ");
        $this->execute('
            INSERT INTO boats_new (id, key, display_name, min_berths, max_berths, assistance_required,
                social_preference, flexibility_rank, absence_rank, created_at, updated_at, owner_user_id)
            SELECT b.id, b.key, b.display_name, b.min_berths, b.max_berths, b.assistance_required,
                b.social_preference, b.flexibility_rank, b.absence_rank, b.created_at, b.updated_at,
                (SELECT u.auth_user_id FROM users u WHERE u.id = b.owner_user_id)
            FROM boats b
        ');

        // Dropping the old tables also drops their indexes and triggers
        foreach (['crews', 'boats', 'users'] as $table) {
            $this->execute("DROP TABLE {$table}");
        }
        foreach (['users', 'crews', 'boats'] as $table) {
            $this->execute("ALTER TABLE {$table}_new RENAME TO {$table}");
        }

        $this->execute('CREATE INDEX `idx_users_account_type` ON `users` (`account_type` ASC)');
        $this->execute('CREATE INDEX `idx_crews_partner` ON `crews` (`partner_key` ASC)');
        $this->execute('CREATE UNIQUE INDEX `idx_crews_key` ON `crews` (`key` ASC)');
        $this->execute('CREATE INDEX `idx_crews_user_id` ON `crews` (`user_id` ASC)');
        $this->execute('CREATE UNIQUE INDEX `idx_boats_key` ON `boats` (`key` ASC)');
        $this->execute('CREATE INDEX `idx_boats_owner_user_id` ON `boats` (`owner_user_id` ASC)');

        $this->execute('
            CREATE TRIGGER users_updated_at
            AFTER UPDATE ON users
            BEGIN
                UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE auth_user_id = NEW.auth_user_id;
            END
        ');
        foreach (['crews', 'boats'] as $table) {
            $this->execute("
                CREATE TRIGGER {$table}_updated_at
                AFTER UPDATE ON {$table}
                BEGIN
                    UPDATE {$table} SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
                END
            ");
        }

        // Keep AUTOINCREMENT from reusing crew / boat IDs that were handed out before
        foreach ($sequences as $table => $seq) {
            if ($seq !== null) {
                $this->execute("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                $this->execute("INSERT INTO sqlite_sequence (name, seq) VALUES ('{$table}', {$seq})");
            }
        }
    }
}
