<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Renames users.is_admin to users.is_program_admin.
 *
 * "Admin" is ambiguous now that sailConnect has platform admins (auth.users.is_platform_admin); this column
 * is the Sail Friday program admin. The API field and entity method keep their names.
 */
final class RenameUsersIsAdmin extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE users RENAME COLUMN is_admin TO is_program_admin');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE users RENAME COLUMN is_program_admin TO is_admin');
    }
}
