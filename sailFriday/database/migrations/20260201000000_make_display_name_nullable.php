<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Make Display Name Nullable Migration
 *
 * Alters display_name columns in boats and crews tables to allow NULL values.
 *
 * Purpose: Make displayName optional throughout the system.
 * This allows users to register without providing a display name, which can be
 * added later if desired. Both boat and crew display names become optional fields.
 */
final class MakeDisplayNameNullable extends AbstractMigration
{
    /**
     * Change Method: Make display_name columns nullable
     */
    public function change(): void
    {
        // ====================================================================
        // Table: boats
        // Make display_name column nullable
        // ====================================================================
        $this->table('boats')
             ->changeColumn('display_name', 'string', [
                 'limit' => 255,
                 'null' => true,
             ])
             ->update();

        // ====================================================================
        // Table: crews
        // Make display_name column nullable
        // ====================================================================
        $this->table('crews')
             ->changeColumn('display_name', 'string', [
                 'limit' => 255,
                 'null' => true,
             ])
             ->update();
    }
}
