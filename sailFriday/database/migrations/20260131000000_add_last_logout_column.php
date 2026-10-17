<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add Last Logout Column Migration
 *
 * Adds last_logout timestamp to users table for logout tracking.
 *
 * Purpose: Enable server-side logout tracking for audit trail and analytics.
 * The last_logout field provides a timestamp of when the user last logged out,
 * which can be used for:
 * - Audit trails and compliance reporting
 * - Session analytics
 * - Future token revocation/reuse detection
 */
final class AddLastLogoutColumn extends AbstractMigration
{
    /**
     * Add last_logout column to users table
     */
    public function change(): void
    {
        // ====================================================================
        // Table: users
        // Add last_logout timestamp column
        // ====================================================================
        $this->table('users')
             ->addColumn('last_logout', 'datetime', [
                 'null' => true,
                 'after' => 'last_login',
             ])
             ->update();
    }
}
