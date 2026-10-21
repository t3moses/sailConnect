-- sailConnect auth schema (applied by the Phinx migration and by the tests)

CREATE TABLE users (
    user_id            TEXT PRIMARY KEY,
    email_address      TEXT NOT NULL COLLATE NOCASE,
    password_hash      TEXT NOT NULL,
    first_name         TEXT NOT NULL,
    last_name          TEXT NOT NULL,
    display_name       TEXT NOT NULL COLLATE NOCASE,
    is_member          INTEGER NOT NULL DEFAULT 0,
    membership_number  TEXT,
    mobile_number      TEXT,
    is_platform_admin  INTEGER NOT NULL DEFAULT 0,
    is_platform_active INTEGER NOT NULL DEFAULT 1,
    terms_accepted_at  TEXT,
    terms_url          TEXT,
    created_at         TEXT NOT NULL,
    updated_at         TEXT NOT NULL,
    CHECK (is_member = 0 OR membership_number IS NOT NULL)
            );

CREATE UNIQUE INDEX idx_users_email ON users (email_address);

CREATE UNIQUE INDEX idx_users_display_name ON users (display_name);

CREATE TABLE sessions (
    session_hash TEXT PRIMARY KEY,
    user_id      TEXT NOT NULL REFERENCES users (user_id) ON DELETE CASCADE,
    created_at   TEXT NOT NULL,
    last_touch   TEXT NOT NULL,
    ip_address   TEXT,
    user_agent   TEXT
            );

CREATE INDEX idx_sessions_user ON sessions (user_id);

CREATE TABLE tokens (
    token_hash TEXT PRIMARY KEY,
    user_id    TEXT NOT NULL REFERENCES users (user_id) ON DELETE CASCADE,
    purpose    TEXT NOT NULL,
    payload    TEXT,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL
            );

CREATE INDEX idx_tokens_user_purpose ON tokens (user_id, purpose);

CREATE TABLE login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    email_address TEXT NOT NULL COLLATE NOCASE,
    ip_address   TEXT,
    attempted_at TEXT NOT NULL
            );

CREATE INDEX idx_login_attempts ON login_attempts (email_address, attempted_at);
