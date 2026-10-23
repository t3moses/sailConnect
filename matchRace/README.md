# Match Race

Dummy sailConnect application that matches boat owners and crew. It looks like Sail Friday (same stylesheet and layout) and uses
the same single sign-on: sign-in, registration, sign-out and the account profile belong to `../auth`.

`database/matchRace.db` (created on first request, git-ignored) has one table, `users`: `auth_user_id` (the sailConnect user ID, primary key)
and `user_type` (`boat_owner` or `crew`). Email and names are read from the auth database, which is attached as schema `auth`
(see `src/Database.php`).

## Flow

1. **Join Now** (`/join`, or `/register`): a signed-out visitor goes to the auth service's `/start` page and enters an email address.
   An existing account continues to sign-in, a new email to registration, and either way the visitor returns to `/join`.
2. `/join` (signed in, no Match Race record): pick boat owner or crew, click "Let's Go!", land on the landing page.
3. A signed-in user without a record is redirected from `/` and `/dashboard` to `/join`. A user with a record is redirected from `/join` to `/`.
4. **Sign In** goes to the auth sign-in page; there is no app-owned sign-in page. `/dashboard` shows the profile and the boat owner and crew lists.

## Local development

```bash
composer install
cp .env.example .env            # set AUTH_DB_PATH if auth/.env points at the production path
php -S localhost:8002 -t public public/index.php
```

Run the auth service too (`php -S localhost:8001 -t public public/index.php` in `../auth`) and add `localhost` to its
`ALLOWED_RETURN_HOSTS`. Cookies are shared across ports on `localhost`, so leave `COOKIE_DOMAIN` empty there.
Production serves `public/` at `matchRace.sailConnect.org`.
