# Match Race

Dummy sailConnect application that matches boat owners and crew. It looks like Sail Friday (same stylesheet and layout) and uses
the same single sign-on: sign-in, registration, sign-out and the account profile belong to `../auth`.

`database/matchRace.db` (created on first request, git-ignored) has one table, `users`: `auth_user_id` (the sailConnect user ID, primary key)
and `user_type` (`boat_owner` or `crew`). Email and names are read from the auth database, which is attached as schema `auth`
(see `src/Database.php`).

## Flow

1. **Join Now** (`/join`): pick boat owner or crew. Signed-out visitors are sent to the auth registration page and return to
   `/join?type=...` with their choice preselected; signed-in visitors are enrolled straight away.
2. `/dashboard` shows the profile and the lists of boat owners and crew. It redirects to `/join` for a signed-in user who has not joined.

## Local development

```bash
composer install
cp .env.example .env            # set AUTH_DB_PATH if auth/.env points at the production path
php -S localhost:8002 -t public public/index.php
```

Run the auth service too (`php -S localhost:8001 -t public public/index.php` in `../auth`) and add `localhost` to its
`ALLOWED_RETURN_HOSTS`. Cookies are shared across ports on `localhost`, so leave `COOKIE_DOMAIN` empty there.
Production serves `public/` at `matchRace.sailConnect.org`.
