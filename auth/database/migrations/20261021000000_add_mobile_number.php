<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddMobileNumber extends AbstractMigration
{
    public function up(): void
    {
        // A fresh install already has the column (the first migration loads schema.sql).
        $has = array_filter(
            $this->fetchAll('PRAGMA table_info(users)'),
            static fn (array $c): bool => $c['name'] === 'mobile_number'
        );
        if (!$has) {
            $this->execute('ALTER TABLE users ADD COLUMN mobile_number TEXT');
        }
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE users DROP COLUMN mobile_number');
    }
}
