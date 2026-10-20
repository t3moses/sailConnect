# auth — sailConnect single sign-on

Identity service for all sailConnect applications (sailFriday, matchRace, ...). It owns the common
identity attributes, registration, sign-in/sign-off, password reset and profile pages. Applications
keep their own databases for application-specific data, keyed by `user_id`.

## Setup

```bash
composer install
cp .env.example .env            # then edit
vendor/bin/phinx migrate
vendor/bin/phinx seed:run -s PlatformAdminSeeder   # first platform admin from SEED_ADMIN_*
php -S localhost:8001 -t public public/index.php   # development
```

Production: serve `public/` at `auth.sailConnect.org`, set `COOKIE_DOMAIN=.sailConnect.org`,
`APP_URL=https://auth.sailConnect.org`, and give the web-server user write access to the database file and its directory
(SQLite WAL needs to create `-wal`/`-shm` files).

## How SSO works

- Sign-in creates a row in `sessions` and sets an `HttpOnly; Secure; SameSite=Lax` cookie on the parent domain,
  so every `*.sailConnect.org` application receives it. Only the SHA-256 of the token is stored.
- A session ends on sign-off (all apps at once), after `INACTIVITY_EXPIRY_MINUTES` without use, after
  `SESSION_MAX_HOURS`, or when an admin deactivates the account.
- `isSignedOn` is derived: a user is signed on while at least one live session exists.

## Using it from an application

```php
require '/path/to/auth/vendor/autoload.php';
$auth = SailConnect\Auth\Client\SessionVerifier::fromAuthDir('/path/to/auth');
$user = $auth->requireUser();    // redirects to auth.sailConnect.org/signin?return=... when signed out
// $user: user_id, email_address, first_name, last_name, display_name, is_member,
//        membership_number, is_platform_admin, is_platform_active
```

The verifier reads the auth database directly (same server) and only writes `sessions.last_touch`.
Sign-in redirects back only to hosts under `ALLOWED_RETURN_HOSTS`.

## .env

`CLUB_ADMIN_EMAIL`, `INACTIVITY_EXPIRY_MINUTES`, `TERMS_OF_USE_URL` as specified, plus cookie, Turnstile,
SMTP and seed settings; see `.env.example`.
