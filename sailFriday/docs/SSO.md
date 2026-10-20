# sailConnect Single Sign-On

Sail Friday does not manage credentials. Sign-in, sign-out, registration, password reset and the account profile
belong to the **auth** service in `../auth` (see its README). One session works across every sailConnect application
(`sailFriday.sailConnect.org`, `matchRace.sailConnect.org`, ...) and ends on sign-off, inactivity, or deactivation.

## How a request is authenticated

1. The browser sends the `sc_session` cookie (`HttpOnly`, set by auth on `.sailConnect.org`).
2. `AuthSessionMiddleware` asks the auth library (`SailConnect\Auth\Client\SessionVerifier`) to validate it. This reads the
   auth database directly (same server) and slides the inactivity window.
3. The identity is mapped to the local `users` row through `users.auth_user_id`. Sail Friday keeps its own integer
   `users.id`, so crews, boats and history are untouched.
4. No session -> `401 authentication_required`. Valid session but no local row -> `403 enrollment_required`.

Routes marked `'auth' => 'identity'` in `config/routes.php` only need a signed-in sailConnect user (enrollment).

## What lives where

| Data | Owner | Notes |
|------|-------|-------|
| Email, password, first/last name, display name, membership | auth | Edited at `auth.sailConnect.org/profile` |
| Platform admin / platform active | auth | Deactivating there signs the user out everywhere |
| Account type (crew / boat owner), `is_admin`, crew and boat profile | Sail Friday | `is_admin` is the Sail Friday admin, separate from platform admin |

`users.email` and the crew `membership_number` are local copies, refreshed on each authenticated request and by
`bin/sync-identity.php` (run it from cron so reminders see changes made in auth):

```
*/15 * * * * php /var/www/html/sailConnect/sailFriday/bin/sync-identity.php >> /var/www/html/sailConnect/sailFriday/logs/cron.log 2>&1
```

Names are **not** synced: a crew key is derived from the name and is referenced by partners, availability and history.
A name change in auth therefore does not rename the existing crew record. Admins can still edit a crew record under
`/api/admin/crews/{crewKey}`, but a membership number edited there is overwritten by auth on the user's next request.

## First-time setup

1. Install and migrate auth (`../auth/README.md`), create the first platform admin with its seeder.
2. In this project: `composer install` (pulls in `../auth` as a path package), then `vendor/bin/phinx migrate`
   (adds `users.auth_user_id`).
3. **Back up both databases**, then migrate the existing users:

   ```bash
   php bin/migrate-users-to-auth.php                                   # dry run: report only
   php bin/migrate-users-to-auth.php --apply --platform-admin=you@example.org
   ```

   Passwords carry over (bcrypt hashes are copied). Names come from the crew / boat-owner profile, and `created_at` is
   preserved. The script lists anything needing attention, such as membership numbers that are not a valid format
   (those users are migrated as non-members). It is safe to re-run.
4. Add the cron entry above.

## Local development

Run both servers: `php -S localhost:8001 -t public public/index.php` in `auth/` and
`php -S localhost:8000 -t public public/index.php` here. Cookies are shared across ports on `localhost`,
so leave `COOKIE_DOMAIN` empty in `auth/.env`. The front end finds the auth service at the same host on port 8001
(override with `window.SAILCONNECT_AUTH_URL`).

## Front end

- `js/ssoService.js` builds links to the auth service (sign in, register, sign out, profile).
- The signed-in navigation is chosen from the readable `sc_signed_in` hint cookie set by auth; it is only a hint, the API
  decides. A `401` clears the hint and redirects to sign-in. A `403 enrollment_required` redirects to `account.html`.
- `signin.html`, `forgot-password.html` and `reset-password.html` only forward to the auth service.

## Tests

The API suite (`--testsuite=API`) creates identities and sessions directly in the auth database that `../auth/.env`
points to, so run it against a development setup, not production.
