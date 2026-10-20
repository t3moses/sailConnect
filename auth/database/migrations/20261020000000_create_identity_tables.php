<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateIdentityTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE users (
                user_id            TEXT PRIMARY KEY,
                email_address      TEXT NOT NULL COLLATE NOCASE,
                password_hash      TEXT NOT NULL,
                first_name         TEXT NOT NULL,
                last_name          TEXT NOT NULL,
                display_name       TEXT NOT NULL COLLATE NOCASE,
                is_member          INTEGER NOT NULL DEFAULT 0,
                membership_number  TEXT,
                is_platform_admin  INTEGER NOT NULL DEFAULT 0,
                is_platform_active INTEGER NOT NULL DEFAULT 1,
                terms_accepted_at  TEXT,
                terms_url          TEXT,
                legacy_ref         TEXT,
                created_at         TEXT NOT NULL,
                updated_at         TEXT NOT NULL,
                CHECK (is_member = 0 OR membership_number IS NOT NULL)
            )
        ");
        $this->execute('CREATE UNIQUE INDEX idx_users_email ON users (email_address)');
        $this->execute('CREATE UNIQUE INDEX idx_users_display_name ON users (display_name)');

        // A user is "signed on" while at least one unexpired row exists here.
        $this->execute("
            CREATE TABLE sessions (
                session_hash TEXT PRIMARY KEY,
                user_id      TEXT NOT NULL REFERENCES users (user_id) ON DELETE CASCADE,
                created_at   TEXT NOT NULL,
                last_touch   TEXT NOT NULL,
                ip_address   TEXT,
                user_agent   TEXT
            )
        ");
        $this->execute('CREATE INDEX idx_sessions_user ON sessions (user_id)');

        // Single-use tokens: purpose is 'password_reset' or 'email_change' (payload = new address).
        $this->execute("
            CREATE TABLE tokens (
                token_hash TEXT PRIMARY KEY,
                user_id    TEXT NOT NULL REFERENCES users (user_id) ON DELETE CASCADE,
                purpose    TEXT NOT NULL,
                payload    TEXT,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
        $this->execute('CREATE INDEX idx_tokens_user_purpose ON tokens (user_id, purpose)');

        $this->execute("
            CREATE TABLE login_attempts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                email_address TEXT NOT NULL COLLATE NOCASE,
                ip_address   TEXT,
                attempted_at TEXT NOT NULL
            )
        ");
        $this->execute('CREATE INDEX idx_login_attempts ON login_attempts (email_address, attempted_at)');
    }

    public function down(): void
    {
        foreach (['login_attempts', 'tokens', 'sessions', 'users'] as $t) {
            $this->execute("DROP TABLE IF EXISTS $t");
        }
    }
}
