-- Match Race application data. Identity (email, name, password, ...) lives in the auth service;
-- users.auth_user_id is the sailConnect user ID.
CREATE TABLE IF NOT EXISTS users (
    auth_user_id TEXT PRIMARY KEY,
    user_type    TEXT NOT NULL CHECK (user_type IN ('boat_owner', 'crew')),
    created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);
