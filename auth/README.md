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
// $user: user_id, email_address, first_name, last_name, display_name, mobile_number, is_member,
//        membership_number, is_platform_admin, is_platform_active
```

The verifier reads the auth database directly (same server) and only writes `sessions.last_touch`; the web-server
user therefore needs write access to the database. It reads `auth/.env` only (never the calling application's
environment), so the application's own `DB_PATH`, `APP_URL` etc. cannot interfere.
Sign-in redirects back only to hosts under `ALLOWED_RETURN_HOSTS`.

Besides the `HttpOnly` session cookie, sign-in sets a readable `sc_signed_in=1` marker so front ends can choose
signed-in or signed-out navigation synchronously. It is only a hint; the verifier clears it when it finds the session invalid.

sailFriday integrates through this library (`../sailFriday/docs/SSO.md`), and migrates existing users with
`php bin/migrate-users-to-auth.php` there.

Applications on the same server may also read the auth database directly for identity data instead of keeping copies;
sailFriday attaches it to its own SQLite connection and joins on `users.auth_user_id`.

## Mobile number

Optional. Digits with spaces, hyphens, dots, parentheses and a leading plus, 7 to 20 characters. Collected at registration and
on the profile page; applications such as sailFriday require it for features that depend on it (boat owners, WhatsApp group).

## Membership numbers

Letters, digits and hyphens, up to 20 characters, starting with a letter or digit (the club format is like `7060-13489`).

## Tests

```bash
vendor/bin/phpunit
```

## .env

`CLUB_ADMIN_EMAIL`, `INACTIVITY_EXPIRY_MINUTES`, `TERMS_OF_USE_URL` as specified, plus cookie, Turnstile,
SMTP and seed settings; see `.env.example`.

### Cloudflare Turnstile

Registration is protected by Turnstile. Create a widget in the Cloudflare dashboard (Turnstile) and put its keys in `.env`:

```
TURNSTILE_SITE_KEY=<your-site-key>
TURNSTILE_SECRET_KEY=<your-secret-key>
```

`.env.example` ships Cloudflare's always-pass test keys for development. `.env` is git-ignored: never commit real keys.
