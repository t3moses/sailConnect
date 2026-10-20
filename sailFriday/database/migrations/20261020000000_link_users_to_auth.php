<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Links sailFriday users to the shared sailConnect identity (auth service).
 *
 * Credentials now live in auth. users.password_hash is kept only because the column is NOT NULL;
 * it is no longer read. Existing users are linked by bin/migrate-users-to-auth.php.
 */
final class LinkUsersToAuth extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE users ADD COLUMN auth_user_id TEXT NULL');
        $this->execute('CREATE UNIQUE INDEX idx_users_auth_user_id ON users (auth_user_id)');
    }

    public function down(): void
    {
        $this->execute('DROP INDEX idx_users_auth_user_id');
        $this->execute('ALTER TABLE users DROP COLUMN auth_user_id');
    }
}
