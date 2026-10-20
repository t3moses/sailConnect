<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateIdentityTables extends AbstractMigration
{
    public function up(): void
    {
        // Single source of truth for the schema; the test suite loads the same file.
        $this->execute((string) file_get_contents(dirname(__DIR__) . '/schema.sql'));
    }

    public function down(): void
    {
        foreach (['login_attempts', 'tokens', 'sessions', 'users'] as $t) {
            $this->execute("DROP TABLE IF EXISTS $t");
        }
    }
}
