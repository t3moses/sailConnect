<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropLegacyRef extends AbstractMigration
{
    public function up(): void
    {
        // A fresh install no longer has the column (the first migration loads schema.sql).
        $has = array_filter(
            $this->fetchAll('PRAGMA table_info(users)'),
            static fn (array $c): bool => $c['name'] === 'legacy_ref'
        );
        if ($has) {
            $this->execute('ALTER TABLE users DROP COLUMN legacy_ref');
        }
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE users ADD COLUMN legacy_ref TEXT');
    }
}
