# sailConnect Single Sign-On

Sail Friday does not manage credentials. Sign-in, sign-out, registration, password reset and the account profile
belong to the **auth** service in `../auth` (see its README). One session works across every sailConnect application
(`sailFriday.sailConnect.org`, `matchRace.sailConnect.org`, ...) and ends on sign-off, inactivity, or deactivation.

## How a request is authenticated

1. The browser sends the `sc_session` cookie (`HttpOnly`, set by auth on `.sailConnect.org`).
2. `AuthSessionMiddleware` asks the auth library (`SailConnect\Auth\Client\SessionVerifier`) to validate it. This reads the
   auth database directly (same server) and slides the inactivity window.
3. The identity is mapped to the local `users` row, whose primary key is the sailConnect user ID (`users.auth_user_id`).
   There is no separate local user number: `crews.user_id` and `boats.owner_user_id` hold the same ID, the admin API
   (`/api/admin/users/{userId}`) uses it, and `users.id` no longer exists. Crew and boat rows keep their own integer IDs,
   so availability, history and whitelists are untouched.
4. No session -> `401 authentication_required`. Valid session but no local row -> `403 enrollment_required`.

Routes marked `'auth' => 'identity'` in `config/routes.php` only need a signed-in sailConnect user (enrollment).

## What lives where

| Data | Owner | Notes |
|------|-------|-------|
| Email, password, first/last name, display name, mobile number, membership | auth | Edited at `auth.sailConnect.org/profile`. Sail Friday stores none of these |
| Platform admin / platform active | auth | Deactivating there signs the user out everywhere |
| Account type (crew / boat owner), `is_program_admin`, crew and boat profile | Sail Friday | `is_program_admin` is the Sail Friday admin, separate from platform admin |

## How Sail Friday reads identity data

`sailFriday.db` has no email, password, name, mobile number or membership columns. Every database connection attaches the
auth database as schema `auth` (`AuthDatabase`, path from `AUTH_DB_PATH`, else the `DB_PATH` in `AUTH_DIR/.env`) and creates
three temporary views that join on the sailConnect user ID (`users.auth_user_id`, `crews.user_id`, `boats.owner_user_id`):

| View | Adds to the base table |
|------|------------------------|
| `users_full` | `email` (only users whose auth account exists are visible) |
| `crews_full` | `first_name`, `last_name`, `mobile`, `membership_number` |
| `boats_full` | `owner_first_name`, `owner_last_name`, `owner_mobile` |

Repositories read the views and write only the base tables, so a change made in auth (email, name, mobile, membership) shows
up on the next request and in cron emails with no sync job. The crew membership rank is computed from the live membership number.
The auth database must be on the same server and the web-server user needs read access to it.

A crew's `key` is derived from the name at registration and is referenced by partners, availability and history, so it is
**not** renamed when the name changes in auth; the displayed name follows auth. Flex detection (boat owner who is also crew)
therefore matches on the shared user account as well as on the key.

Boat owners and crew who join the WhatsApp group need a mobile number in their sailConnect profile; registration returns a
validation error pointing there when it is missing. Sail Friday's edit-profile page no longer edits any of these fields.

## First-time setup

1. Install and migrate auth (`../auth/README.md`), create the first platform admin with its seeder.
2. In this project: `composer install` (pulls in `../auth` as a path package), then `vendor/bin/phinx migrate`
   (adds `users.auth_user_id`).
3. **Back up both databases**, then migrate the existing users:

   ```bash
   php bin/migrate-users-to-auth.php                                   # dry run: report only
   php bin/migrate-users-to-auth.php --apply --platform-admin=you@example.org
   ```

   Passwords carry over (bcrypt hashes are copied). Names and mobile numbers come from the crew / boat-owner profile, and
   `created_at` is preserved. The script lists anything needing attention, such as membership numbers that are not a valid format
   (those users are migrated as non-members). It is safe to re-run.
4. Migrate auth (`vendor/bin/phinx migrate` in `../auth` adds `users.mobile_number`), then run
   `vendor/bin/phinx migrate` here again. Migration `20261021160000_use_auth_user_id_as_user_key` then rebuilds `users`, `crews` and
   `boats` so `auth_user_id` is the primary key and `crews.user_id` / `boats.owner_user_id` hold it (it also refuses to run with an
   unlinked user and checks foreign keys afterwards). Migration `20261021120000_move_identity_to_auth` **refuses to run while any user is
   unlinked**, copies every crew / boat-owner mobile number into auth (never overwriting one already there, and verifying the
   copy), and only then drops `users.email`, `users.password_hash`, the crew name / mobile / membership columns, the boat owner
   name / mobile columns and the unused `password_reset_tokens` table. It is irreversible: back up `sailFriday.db` first.

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
points to, so run it against a development setup, not production. The Integration suite builds an in-memory `auth`
schema (`IntegrationTestCase::createIdentity()`, `createLinkedUser()`, `linkIdentity()`).
