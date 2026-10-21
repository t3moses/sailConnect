<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Drops users.last_login and users.last_logout.
 *
 * Sign-in and sign-out belong to sailConnect (auth sessions); Sail Friday has not set either column since the
 * move to single sign-on, so the remaining values are stale.
 */
final class DropUsersLastLoginLogout extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE users DROP COLUMN last_login');
        $this->execute('ALTER TABLE users DROP COLUMN last_logout');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE users ADD COLUMN last_login DATETIME_TEXT NULL');
        $this->execute('ALTER TABLE users ADD COLUMN last_logout DATETIME_TEXT NULL');
    }
}
