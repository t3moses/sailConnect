# Runbook: switch production sailFriday to sailConnect single sign-on

Status: **executed on 2026-10-21** (about 2 minutes of 503, 17:00 EDT). Platform admin: `t3moses@icloud.com` only. Cron jobs were deliberately not
installed (season over; clock still in simulated mode). Old tree kept at `/var/www/sailFriday.old` until about 2026-10-28; pre-cutover database backups are in
`~admin/backups/`. Prepared 2026-10-20 from a read-only inspection of the production server
(`admin@16.54.93.63`). Revised 2026-10-21: sailFriday no longer stores email, password, names, mobile number or membership
(see [SSO.md](SSO.md#how-sail-friday-reads-identity-data)), so auth is deployed with the new `mobile_number` column and the
identity-move migration runs after the users are linked. Background: [SSO.md](SSO.md), [DEPLOYMENT.md](DEPLOYMENT.md#deploying-the-sailconnect-auth-service).

## What is being changed

| | Before | After |
|---|---|---|
| Sign-in | sailFriday JWT login (`/api/auth/login`) | Shared sailConnect session cookie from `auth.sailConnect.org` |
| Passwords | `sailFriday.db` `users.password_hash` | `auth.db` (the 120 existing bcrypt hashes are copied, so passwords keep working) |
| Email, names, mobile, membership | Columns in `sailFriday.db` (`users`, `crews`, `boats`) | `auth.db` only; sailFriday reads them through an attached connection. Mobile numbers are copied into the new `auth.users.mobile_number` first |
| Identity sync cron | n/a | None needed (nothing is copied any more) |
| Code on server | Older copy of the repo (cosmetic "JAWS" naming; no hotfixes found) | Current repo (`HEAD`) |

Expected downtime: about 10-15 minutes (sailFriday shows a 503 "back shortly" page). Existing sessions end; each user signs in once
at the auth site with their **existing password**. Users whose email already exists in auth keep the **auth** password
(currently only `t3moses@icloud.com`).

## What I found on the server (facts the plan relies on)

- App directories are `/var/www/sailFriday`, `/var/www/auth`, `/var/www/matchRace`, `/var/www/sailConnect`. Apache vhost `sailfriday.conf`
  serves `/var/www/sailFriday/public` with `AllowOverride All`, so **no Apache change is needed**.
- Production DB has the same 16 migrations as the repo (`phinxlog` up to `20260828103827`), 120 users / 101 crews / 19 boats / 18 events.
  `users` was last changed 2026-10-19, so data is live. The new migration (`auth_user_id`) is additive.
- Server has **no Composer and no git**: `vendor/` is built locally and uploaded. Apache loads PHP 8.4, CLI is 8.5; both have `pdo_sqlite`, `curl`, `mbstring`.
- **No cron entries exist** for sailFriday (`bin/notify.php`, `bin/recalculate.php` are not scheduled). Reminder emails are not currently being sent.
  Only the new identity-sync entry is added by this runbook; scheduling the others is a separate decision.
- `sailFriday/bitnami password.txt` is **tracked in git** (it is in the repo history). It is excluded from the release; consider rotating whatever it contains and removing it from the repo.
- Stray files on the server will be left behind in the old directory: `Libraries/`, `MailTest.php`, `bitnami password.txt`, the `phpmyadmin`
  symlink, and **`public/debug_jwt.php`** (a JWT debug script inside the web root).
- `.env` has `APP_URL=http://16.54.93.63` (wrong; used for links in notification emails) and placeholder `CORS_ALLOWED_ORIGINS`. Both are fixed below.
- Migration dry-run on a copy of the data: all 120 users migrate; three have free-text membership numbers and become non-members (listed in the output).

## Decisions needed before starting

1. **Window.** Pick a quiet time, not an event day during the blackout window (default 10:00-18:00 on Fridays).
2. **Platform admins.** Which sailFriday admins also become sailConnect platform admins? (`t3moses@icloud.com` already is; `afk_26@hotmail.com` is the other
   sailFriday admin.) Platform admins can deactivate accounts everywhere and receive every registration email.
3. **Uncommitted local files.** `admin-notifications.html` and `debug.html` have local edits that are not
   committed. The release is built from `git archive HEAD`, so those edits are **not** deployed unless you commit them first.
4. **Cron.** Do you also want the notification/recalculate cron jobs scheduled? (Not part of this change.)

## Pre-flight (the day before / morning of; no user impact)

- [ ] Register a throwaway account in a browser at `https://auth.sailconnect.org/register` to confirm the Turnstile widget loads, the
      welcome email arrives, and the admin notification email reaches `t3moses@gmail.com`; then deactivate it at `/admin/users`.
- [ ] `t3moses@icloud.com` can sign in at the auth site with the password set through the reset link.
- [ ] Local tests green: `vendor/bin/phpunit --testsuite=Unit` (auth and sailFriday), integration suite (6 known pre-existing failures), PHPStan.
- [ ] Working tree clean for the release (`git status`), `HEAD` is what you expect.

## Cutover

Helpers used below (run locally from the repo root):

```bash
K=LightsailDefaultKey-ca-central-1.pem; H=admin@16.54.93.63
srv() { ssh -i $K $H "$@"; }
S=<scratchpad>/release; rm -rf $S; mkdir -p $S
```

### 1. Build the release locally (no impact)

```bash
git archive HEAD sailFriday auth | tar -x -C $S
( cd $S/sailFriday && composer install --no-dev --optimize-autoloader --no-interaction )
ls -l $S/sailFriday/vendor/sailconnect/       # auth -> ../../../auth  (must be a RELATIVE symlink)
php -r 'require "'$S'/sailFriday/vendor/autoload.php"; var_dump(class_exists("SailConnect\\Auth\\Client\\SessionVerifier"));'
```

If the symlink is absolute, replace it after upload with `ln -sfn ../../../auth /var/www/sailFriday-new/vendor/sailconnect/auth`.
Do not upload `auth/` into the sailFriday tree. The live `/var/www/auth` is updated in place in step 2b (code and migration only; never its `.env`, `vendor/` or database).

### 2. Upload as a sibling directory (no impact)

```bash
# Leave out the auth copy, tests, docs, dev-only files and the tracked credentials file
rsync -a --exclude 'auth' --exclude 'tests/' --exclude 'docs/' --exclude 'bitnami password.txt' \
  --exclude 'INTEGRATION_TESTS_STATUS.md' --exclude 'CLAUDE.md' --exclude 'package*.json' --exclude 'eslint.config.mjs' \
  --exclude 'phpunit.xml' --exclude 'phpstan*.neon' --exclude 'phpcs.xml' --exclude 'database/seeds/' \
  -e "ssh -i $K" $S/sailFriday/ $H:/var/www/sailFriday-new/
srv 'cd /var/www/sailFriday-new && ls vendor/sailconnect/ && mkdir -p logs database/backups'
```

The release is built from `git archive`, so it contains no database files; the live DB is copied in step 4.

### 2b. Update the auth service in place (adds the mobile number; no user impact)

The new column is additive and old auth code ignores it, so this can be done before the window. The updated register / profile pages
ask for an optional mobile number.

```bash
rsync -a --exclude '.env' --exclude 'vendor/' --exclude 'database/*.db*' --exclude 'var/' --exclude 'tests/' \
  -e "ssh -i $K" $S/auth/ $H:/var/www/auth/
srv 'cd /var/www/auth && sudo -u www-data php vendor/bin/phinx migrate && sqlite3 database/auth.db "pragma table_info(users)" | grep mobile_number'
```

Re-run the pre-flight check afterwards: register / profile pages load and a throwaway account can save a mobile number.

### 3. Prepare `.env` (no impact)

```bash
srv 'cd /var/www/sailFriday-new
cp -p /var/www/sailFriday/.env .env
sudo sed -i "/^JWT_SECRET=/d;/^JWT_EXPIRATION_MINUTES=/d" .env
sudo sed -i "s#^APP_URL=.*#APP_URL=https://sailfriday.sailconnect.org#" .env
sudo sed -i "s#^CORS_ALLOWED_ORIGINS=.*#CORS_ALLOWED_ORIGINS=https://sailfriday.sailconnect.org#" .env
grep -E "^(APP_URL|CORS_ALLOWED_ORIGINS|DB_PATH)=" .env'      # DB_PATH must stay /var/www/sailFriday/database/sailFriday.db
```

`DB_PATH` is an absolute path to `/var/www/sailFriday/...`, which is correct after the directory swap in step 6.

### 4. Maintenance window starts: freeze the old site, snapshot, migrate

```bash
# 4a. Show a 503 page on the old site (reversible: step R1 removes it)
srv 'cd /var/www/sailFriday/public && cp .htaccess .htaccess.pre-cutover &&
  { printf "ErrorDocument 503 \"sailFriday is being upgraded and will be back in a few minutes.\"\nRewriteEngine On\nRewriteRule ^ - [R=503,L]\n"; cat .htaccess; } > /tmp/ht && sudo cp /tmp/ht .htaccess'
curl -s -o /dev/null -w "%{http_code}\n" https://sailfriday.sailconnect.org/api/events      # expect 503

# 4b. Backups (both databases), kept off the web root
srv 'ts=$(date +%Y%m%d_%H%M%S); mkdir -p ~/backups
sqlite3 /var/www/sailFriday/database/sailFriday.db ".backup $HOME/backups/sailFriday-precutover-$ts.db"
sqlite3 /var/www/auth/database/auth.db ".backup $HOME/backups/auth-precutover-$ts.db"
chmod 600 ~/backups/*-precutover-$ts.db; ls -la ~/backups/*-precutover-$ts.db'

# 4c. Put the snapshot into the new tree (the old tree's DB stays untouched = rollback point)
srv 'cp ~/backups/sailFriday-precutover-*.db /var/www/sailFriday-new/database/sailFriday.db   # newest only
cd /var/www/sailFriday-new && sudo chown -R admin:www-data . && sudo chmod 775 database logs
sudo chown www-data:www-data database/sailFriday.db && sudo chmod 664 database/sailFriday.db
# Stop at the link migration: the identity-move migration (20261021120000) must wait until step 5 has linked every user
sudo -u www-data php vendor/bin/phinx migrate -e production -t 20261020000000 && sudo -u www-data php vendor/bin/phinx status -e production | tail -4'
```

### 5. Migrate users into auth (dry run first, read the output)

```bash
srv 'cd /var/www/sailFriday-new
sudo -u www-data php bin/migrate-users-to-auth.php                                   # DRY RUN: expect migrated=119, linked_existing=1 (t3moses@icloud.com), skipped=0
'
# If the numbers and the "Needs attention" list match expectations:
srv 'cd /var/www/sailFriday-new
sudo -u www-data php bin/migrate-users-to-auth.php --apply --platform-admin=<comma-separated emails from decision 2>
sudo -u www-data sqlite3 database/sailFriday.db "select count(*) from users where auth_user_id is null"   # expect 0
sudo -u www-data sqlite3 /var/www/auth/database/auth.db "select count(*), sum(is_platform_admin) from users"'
```

Stop and investigate if `skipped > 0`, if any user still has `auth_user_id IS NULL`, or if the dry run differs from the earlier rehearsal.

### 5b. Move identity data out of sailFriday.db (irreversible; the step 4b backup is the way back)

```bash
srv 'cd /var/www/sailFriday-new
sudo -u www-data php vendor/bin/phinx migrate -e production       # identity move, renames/drops, then 20261021160000 (auth_user_id becomes the users primary key)
sudo -u www-data sqlite3 database/sailFriday.db "pragma table_info(users)" "pragma table_info(crews)" "pragma table_info(boats)" | grep -E "email|password|first_name|last_name|mobile|membership_number"   # expect no output
sudo -u www-data sqlite3 /var/www/auth/database/auth.db "select count(*), sum(coalesce(mobile_number,\"\")<>\"\") from users"'
```

Also check that the user key moved without losing links (compare with the same counts taken from the step 4b backup before this step):
`select count(*) from crews where user_id is not null` and `select count(*) from boats where owner_user_id is not null` are unchanged,
`pragma table_info(users)` shows `auth_user_id` as the primary key with no `id` column, and `pragma foreign_key_check` prints nothing.

The migration stops with a message (and changes nothing in sailFriday.db) if any user is unlinked or the auth database lacks
`mobile_number`. It copies mobile numbers without overwriting any already in auth. Expect about 103 users with a mobile number on the
current data (84 crew, 19 boat owners); some values are free text (for example an email address typed into the mobile field for one
boat owner) and will fail validation the next time that person saves their sailConnect profile, which is the prompt to fix them.

### 6. Swap the directories (seconds)

```bash
srv 'cd /var/www && sudo mv sailFriday sailFriday.old && sudo mv sailFriday-new sailFriday && ls -ld sailFriday sailFriday.old'
```

The Apache vhost already points at `/var/www/sailFriday/public`; no reload is required. The `vendor/sailconnect/auth` symlink is relative, so it
resolves to `/var/www/auth` from the new location.

### 7. Smoke tests (still no user traffic expected)

```bash
curl -s -o /dev/null -w "events (public) %{http_code}\n" https://sailfriday.sailconnect.org/api/events          # 200
curl -s -o /dev/null -w "users/me anon  %{http_code}\n"   https://sailfriday.sailconnect.org/api/users/me      # 401
curl -s -o /dev/null -w "old login gone %{http_code}\n" -X POST https://sailfriday.sailconnect.org/api/auth/login   # 404
curl -s -o /dev/null -w "debug_jwt gone %{http_code}\n"   https://sailfriday.sailconnect.org/debug_jwt.php           # not 200
srv 'sudo tail -5 /var/log/apache2/error.log | cut -c1-200; sudo tail -5 /var/www/sailFriday/logs/app-$(date +%Y-%m-%d).log 2>/dev/null | cut -c1-200'
```

Then in a browser (you):
1. `https://sailfriday.sailconnect.org` -> **Sign In** goes to `auth.sailconnect.org`, sign in as a migrated user whose **old password** you know -> back on sailFriday signed in, dashboard loads with their crew/boat profile and events.
2. Open `https://matchrace.sailconnect.org` and `https://sailconnect.org` (unaffected), then `https://auth.sailconnect.org/profile` shows the same session.
3. **Sign out** from sailFriday -> signed out at auth too. Sign in again.
4. Edit profile in sailFriday (skill, experience, boat details): saves; name/email/mobile/password/membership show as managed in sailConnect, and a user's mobile number matches their sailConnect profile.
5. Admin pages (`admin-users.html`, `admin-matching.html`) load for a sailFriday admin.
6. Register a brand-new account at auth, then enroll on sailFriday as crew; check it appears in `admin-users.html` and the registration emails arrive.
7. Leave a session idle past 30 minutes; the next click returns to the auth sign-in page.

### 8. Finish

No identity-sync cron is needed: email, names, mobile and membership are read from auth on every request.

Keep `/var/www/sailFriday.old` (it contains the pre-cutover DB, `debug_jwt.php` and other stray files) for about a week, then archive and delete it:
`sudo chmod -R o-rwx /var/www/sailFriday.old` straight away so the leftover `.env`/`debug_jwt.php` are not readable by other local users (it is outside any DocumentRoot).

Announce to users: "Sail Friday sign-in has moved to sailConnect. Sign in with your usual email and password; one sign-in now works across sailConnect."

## Rollback

Valid for roughly the first hour (after that, changes made in the new system would be lost). The old directory and its database are unchanged.

```bash
srv 'cd /var/www && sudo mv sailFriday sailFriday.failed && sudo mv sailFriday.old sailFriday'
srv 'cd /var/www/sailFriday/public && sudo cp .htaccess.pre-cutover .htaccess'        # R1: remove the 503
curl -s -o /dev/null -w "%{http_code}\n" https://sailfriday.sailconnect.org/api/events
```

Old logins work again (JWT). Users created in auth during the attempt are harmless; the old code ignores `auth_user_id`. The old tree and its
database are untouched (step 4c works on a copy), and the new `auth.users.mobile_number` column is ignored by old code. If the old DB itself were
damaged, restore `~/backups/sailFriday-precutover-*.db` over `/var/www/sailFriday/database/sailFriday.db`.

## Risks and how they are covered

| Risk | Mitigation |
|------|------------|
| Users change data between backup and swap | Old site returns 503 from step 4a, so nothing changes after the snapshot. |
| A user cannot sign in with the old password | Hashes are copied unchanged (bcrypt, verified). Users can use "Forgot your password?" (Mailjet delivery verified). |
| Relative symlink to `../auth` breaks | Checked in step 1 and again by the step 7 smoke tests; `ln -sfn` fix noted. |
| Duplicate-name display names (e.g. Alex Myers x2) | Auth gives them `AlexM` / `AlexM1`; separate accounts, different emails. |
| Three users with free-text membership numbers lose membership rank | Their stored rank is already the "not a valid member" value; listed by the dry run for follow-up. |
| Identity data (email, names, mobile, membership) lost when columns are dropped | Step 5 refuses to proceed with unlinked users; migration 20261021120000 refuses too, copies and verifies mobile numbers before dropping anything, and the step 4b backup restores the old schema. |
| sailFriday cannot read the auth database (path or permissions) | Connection fails fast with "sailConnect auth database not found at …"; the step 7 smoke tests would catch it. Set `AUTH_DB_PATH` in `.env` if auth is not at `/var/www/auth`. |
| Leftover stray files expose secrets | `public/debug_jwt.php` disappears from the web root with the swap; `.old` is locked down in step 8. |
| matchRace | Unaffected; it has no backend yet. |
