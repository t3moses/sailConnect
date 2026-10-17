<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add Users Authentication Migration
 *
 * Adds user authentication system to Sail Friday:
 * - Creates users table for authentication credentials
 * - Links users to crews table (crew members can have user accounts)
 * - Links users to boats table (boat owners can have user accounts)
 * - Adds trigger for users.updated_at timestamp maintenance
 *
 * Purpose: Enable JWT-based authentication for the Sail Friday API
 *
 * Converted from: database/migrations/002_add_users_authentication.sql
 */
final class AddUsersAuthentication extends AbstractMigration
{
    /**
     * Create users table and link to crews/boats
     */
    public function change(): void
    {
        // ====================================================================
        // Table: users
        // Stores user authentication credentials and account information
        // ====================================================================
        $users = $this->table('users');
        $users->addColumn('email', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('account_type', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('is_admin', 'boolean', ['default' => 0])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('last_login', 'datetime', ['null' => true])
              ->addIndex(['email'], ['unique' => true, 'name' => 'idx_users_email'])
              ->addIndex(['account_type'], ['name' => 'idx_users_account_type'])
              ->create();

        // ====================================================================
        // Link Users to Crews
        // Add user_id foreign key to crews table (nullable during migration)
        // ====================================================================
        $this->table('crews')
             ->addColumn('user_id', 'integer', ['null' => true, 'after' => 'updated_at'])
             ->addForeignKey('user_id', 'users', 'id', [
                 'delete' => 'SET_NULL',
                 'update' => 'NO_ACTION'
             ])
             ->addIndex(['user_id'], ['name' => 'idx_crews_user_id'])
             ->update();

        // ====================================================================
        // Link Users to Boats
        // Add owner_user_id foreign key to boats table (nullable during migration)
        // ====================================================================
        $this->table('boats')
             ->addColumn('owner_user_id', 'integer', ['null' => true, 'after' => 'updated_at'])
             ->addForeignKey('owner_user_id', 'users', 'id', [
                 'delete' => 'SET_NULL',
                 'update' => 'NO_ACTION'
             ])
             ->addIndex(['owner_user_id'], ['name' => 'idx_boats_owner_user_id'])
             ->update();

        // ====================================================================
        // Trigger for users.updated_at timestamp maintenance
        // ====================================================================
        $this->execute("
            CREATE TRIGGER IF NOT EXISTS users_updated_at
            AFTER UPDATE ON users
            BEGIN
                UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END
        ");
    }
}
