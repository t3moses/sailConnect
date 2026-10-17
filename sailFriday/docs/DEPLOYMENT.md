# Sail Friday Deployment Guide

Complete guide for deploying Sail Friday to production on AWS Lightsail.

## Table of Contents

- [Pre-Deployment Checklist](#pre-deployment-checklist)
- [AWS Lightsail Deployment](#aws-lightsail-deployment)
- [Server Layout (Debian, Plain Apache)](#server-layout-debian-plain-apache)
- [Environment Configuration](#environment-configuration)
- [Database Management](#database-management)
- [Cron Jobs](#cron-jobs)
- [Monitoring](#monitoring)
  - [Shipping Logs to CloudWatch Logs (Optional)](#shipping-logs-to-cloudwatch-logs-optional)
- [Rollback Procedures](#rollback-procedures)
- [Troubleshooting](#troubleshooting)

---

## Pre-Deployment Checklist

Before deploying to production, verify the following:

- [ ] All tests pass locally: `./vendor/bin/phpunit`
- [ ] Code reviewed and approved via Pull Request
- [ ] Database migrations tested locally
- [ ] Production `.env` file prepared with secure credentials
- [ ] Mailjet API credentials configured and email sending tested
- [ ] Backup of current production database created
- [ ] Deployment window scheduled (avoid event hours 10:00-18:00)
- [ ] Rollback plan prepared

---

## AWS Lightsail Deployment

Sail Friday is deployed on AWS Lightsail on a plain **Debian** instance running a standard Apache + PHP stack (not Bitnami). The server hosts several apps under `/var/www/html/sailConnect/` (`sailFriday`, `matchRace`, `auth`); this guide covers `sailFriday`, whose application root is `/var/www/html/sailConnect/sailFriday`, Apache runs as `www-data:www-data`, and the SSH/deploy user is `admin` (use `ubuntu` on an Ubuntu image).

### Prerequisites

- **AWS Lightsail instance running**
- **SSH key file**: e.g., `LightsailDefaultKey-ca-central-1.pem`
- **SSH access** to the server
- **SFTP client** for file uploads
- **Apache 2.4 + PHP 8.5** (with `pdo_sqlite`, `mbstring`, `curl`, `openssl`, `sqlite3`), `git`/`unzip` and Composer installed on the instance (see Initial Setup)

### Initial Setup (One-Time Configuration)

These steps are only needed when setting up a new server.

#### 1. Install Apache and PHP, Create Application Directory

```bash
sudo apt update
sudo apt install apache2 sqlite3 unzip certbot python3-certbot-dns-route53
# PHP 8.5 and its extensions: install from your PHP package source
# (e.g. the packages.sury.org repo on Debian), then:
sudo apt install php8.5 libapache2-mod-php8.5 php8.5-sqlite3 php8.5-mbstring php8.5-curl php8.5-xml

# Modules required by public/.htaccess (rewrite, expires, deflate)
sudo a2enmod rewrite expires deflate headers ssl
```

The application lives in `/var/www/html/sailConnect/sailFriday`, alongside its sibling apps `matchRace` and `auth`. Only this app's directory is chowned, so sibling apps are untouched:

```bash
sudo mkdir -p /var/www/html/sailConnect/sailFriday
sudo chown -R "$USER":www-data /var/www/html/sailConnect/sailFriday
sudo chmod -R g+w /var/www/html/sailConnect/sailFriday
```

#### 2. Configure Apache Virtual Host

Apache must serve `/var/www/html/sailConnect/sailFriday/public` as this app's DocumentRoot — **not** `/var/www/html/sailConnect/sailFriday` itself, and not `/var/www/html` — so that `src/`, `config/`, `database/`, `logs/` and `.env` are never web-accessible.

**File:** `/etc/apache2/sites-available/sailfriday.conf`

```apache
<VirtualHost *:80>
    ServerName sailfriday.sailconnect.org
    DocumentRoot /var/www/html/sailConnect/sailFriday/public

    <Directory /var/www/html/sailConnect/sailFriday/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sailfriday-error.log
    CustomLog ${APACHE_LOG_DIR}/sailfriday-access.log combined
</VirtualHost>
```

Each app gets its own vhost file on the same pattern, with its own `ServerName` and DocumentRoot: `sailfriday.sailconnect.org` → `.../sailConnect/sailFriday/public`, `matchrace.sailconnect.org` → `.../sailConnect/matchRace/public`, `auth.sailconnect.org` → `.../sailConnect/auth/public` (confirm the final domain for `auth`). DNS: create A records (or one wildcard `*.sailconnect.org`) pointing at the Lightsail static IP.

Enable it and disable the default site:

```bash
sudo a2dissite 000-default   # only if nothing else uses the default site
sudo a2ensite sailfriday
sudo apachectl configtest
sudo systemctl restart apache2
```

**Important Configuration Notes:**

- `AllowOverride All` is **required** for `.htaccess` support (Debian's default is `None` outside the stock `/var/www/` block).
- `mod_rewrite` must be enabled (`a2enmod rewrite`) or every route except `/` will 404.
- The `.htaccess` file in `public/` contains **critical Authorization header forwarding** for JWT authentication:
  ```apache
  RewriteCond %{HTTP:Authorization} .
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  ```
  Without this directive, JWT authentication will fail in production.
- The frontend and API use root-relative URLs (`/api/...`, `/js/...`, `/app/...`), so `sailFriday` needs its **own virtual host** (its own domain or subdomain, or a dedicated port). It cannot be served from a subpath such as `/sailConnect/sailFriday/`. `matchRace` and `auth` get their own vhosts in the same way. Keep `000-default` enabled only if something else needs it; otherwise name-based vhosts are selected by `ServerName`.
- Apache logs are in `/var/log/apache2/` (`sailfriday-error.log`, `sailfriday-access.log`, plus the global `error.log`).

#### 3. Configure SSL/HTTPS with Let's Encrypt

On Debian, certbot's certificates live in `/etc/letsencrypt/live/<domain>/` and Apache can reference them directly — no symlinks are needed.

##### Configure AWS Credentials for DNS Validation

Certbot runs as root and needs the AWS IAM credentials to create DNS records for domain validation. Copy the deploy user's credentials to root:

```bash
sudo mkdir -p /root/.aws
sudo cp /home/admin/.aws/credentials /root/.aws/credentials
sudo cp /home/admin/.aws/config /root/.aws/config
```

##### Issue the Certificate

```bash
sudo certbot certonly \
  --dns-route53 \
  -d sailconnect.org \
  -d '*.sailconnect.org'
```

Certbot uses DNS-01 validation via Route 53 (required for the wildcard) and stores the certificate at `/etc/letsencrypt/live/sailconnect.org/`. One wildcard certificate covers `sailconnect.org`, `sailfriday.sailconnect.org`, `matchrace.sailconnect.org` and `auth.sailconnect.org`, so every app's HTTPS vhost uses the same two certificate lines.

> **Note:** If the certificate has been issued before, certbot may use a numbered directory such as `/etc/letsencrypt/live/sailconnect.org-0001/`. Run `certbot certificates` to confirm the actual path.

##### Configure HTTPS Virtual Host

**File:** `/etc/apache2/sites-available/sailfriday-ssl.conf`

```apache
<VirtualHost *:443>
    ServerName sailfriday.sailconnect.org
    DocumentRoot /var/www/html/sailConnect/sailFriday/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/sailconnect.org/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/sailconnect.org/privkey.pem

    <Directory /var/www/html/sailConnect/sailFriday/public>
        Options -Indexes +FollowSymLinks -MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/sailfriday-error.log
    CustomLog ${APACHE_LOG_DIR}/sailfriday-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite sailfriday-ssl
sudo apachectl configtest
sudo systemctl restart apache2
```

##### Reload Apache After Renewal

DNS-01 validation does not use port 80, so Apache does **not** need to be stopped during renewal. It only needs to reload to pick up the renewed certificate. Add a `renew_hook` to the domain's renewal config:

```bash
sudo nano /etc/letsencrypt/renewal/sailconnect.org.conf
```

Add this line to the `[renewalparams]` section:

```ini
renew_hook = systemctl reload apache2
```

> **Note:** If certbot created a numbered directory (e.g. `sailconnect.org-0001.conf`), edit that file instead — check with `certbot certificates`. Debian's certbot package installs a systemd timer (`certbot.timer`) that runs `certbot renew` automatically.

Confirm renewal works:

```bash
sudo certbot renew --dry-run
sudo systemctl status apache2
```

Then visit the site in a browser and verify the certificate details.

#### 4. Install Composer (if not already installed)

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

Verify installation:
```bash
composer --version
```

### Deployment Steps

Follow these steps for each deployment.

#### Step 1: Add SSH Key to Agent

```bash
ssh-add LightsailDefaultKey-ca-central-1.pem
```

On Windows, use PuTTY Pageant or Windows SSH Agent.

#### Step 2: Backup Production Database

**Always backup before deploying:**

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday/database
sudo cp sailFriday.db backups/sailFriday.backup.$(date +%Y%m%d_%H%M%S).db
ls -lh backups/sailFriday.backup.*
exit
```

Verify the backup was created successfully before proceeding.

#### Step 3: Upload Files via SFTP

Upload the following files and directories to `/var/www/html/sailConnect/sailFriday`:

```bash
sftp admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday

# Upload public folder (all files)
put -r public

# Upload source code
put -r src

# Upload configuration
put -r config

# Upload dependency manifests
put composer.json
put composer.lock

# Upload migration configuration
put phinx.php

# Upload database migrations (required for Phinx)
mkdir -p database/migrations
put -r database/migrations

# Optional: Upload database seeds (only needed for test environments)
# mkdir -p database/seeds
# put -r database/seeds

bye
```

**Files/Folders Uploaded:**

| Path | Contents | Required? |
|------|----------|-----------|
| `public/` | index.php, .htaccess, debug_jwt.php, app/, debug.html | Yes |
| `src/` | Application source code (Domain, Application, Infrastructure, Presentation) | Yes |
| `config/` | config.php, container.php, routes.php | Yes |
| `composer.json` | Dependency manifest | Yes |
| `composer.lock` | Locked dependency versions | Yes |
| `phinx.php` | Migration configuration | Yes |
| `database/migrations/` | Phinx migration files | Yes |
| `database/seeds/` | Test data seeders | Optional (test only) |

**Tip:** To upload specific files only (for hotfixes):
```bash
put src/Domain/Service/SelectionService.php src/Domain/Service/SelectionService.php
```

#### Step 4: Set Initial File Permissions

SSH into the server and set ownership **before** running Composer:

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday

# Code owned by the deploy user, group www-data (Apache); Composer needs write access
sudo chown -R "$USER":www-data /var/www/html/sailConnect/sailFriday
sudo chmod -R g+w /var/www/html/sailConnect/sailFriday
```

This ensures Composer can write to the `vendor/` directory.

#### Step 5: Install Dependencies

**Critical:** Do **NOT** use the `--no-dev` flag:

```bash
cd /var/www/html/sailConnect/sailFriday
composer install --optimize-autoloader
```

**Why no `--no-dev` flag?**

The `--no-dev` flag excludes development dependencies, but **Phinx** (the database migration tool) is listed in `require-dev`. Without Phinx, database migrations cannot be run in production. While this increases deployment size slightly, it's necessary for database management.

**Alternative for maximum security:** Run migrations, then remove development dependencies:
```bash
composer install --optimize-autoloader
vendor/bin/phinx migrate --environment=production
composer install --no-dev --optimize-autoloader
```

#### Step 6: Run Database Migrations (if applicable)

If you have new migrations:

```bash
# Check migration status
vendor/bin/phinx status --environment=production

# Run pending migrations
vendor/bin/phinx migrate --environment=production

# Verify migrations applied successfully
vendor/bin/phinx status --environment=production
```

**Important:** Always test migrations locally first!

#### Step 7: Set Final File Permissions

After Composer and migrations complete, set final permissions:

```bash
# Code is owned by the deploy user, group www-data (Apache can read, not modify)
sudo chown -R "$USER":www-data /var/www/html/sailConnect/sailFriday

# Application code - standard permissions
sudo find /var/www/html/sailConnect/sailFriday/src -type d -exec chmod 755 {} \;
sudo find /var/www/html/sailConnect/sailFriday/src -type f -exec chmod 644 {} \;
sudo find /var/www/html/sailConnect/sailFriday/config -type d -exec chmod 755 {} \;
sudo find /var/www/html/sailConnect/sailFriday/config -type f -exec chmod 644 {} \;

# Public files - readable by web server
sudo find /var/www/html/sailConnect/sailFriday/public -type d -exec chmod 755 {} \;
sudo find /var/www/html/sailConnect/sailFriday/public -type f -exec chmod 644 {} \;

# Database and logs - owned by www-data, which is also the cron user
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/database /var/www/html/sailConnect/sailFriday/database/sailFriday.db
sudo chmod 775 /var/www/html/sailConnect/sailFriday/database
sudo chmod 664 /var/www/html/sailConnect/sailFriday/database/sailFriday.db
sudo mkdir -p /var/www/html/sailConnect/sailFriday/logs
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/logs
sudo chmod 775 /var/www/html/sailConnect/sailFriday/logs
```

**Permission Reference:**

| Item | Permission | Octal | Reason |
|------|------------|-------|--------|
| Database directory | `drwxrwxr-x` | 775 | `www-data` must create WAL/SHM journal files alongside the database |
| Database file | `-rw-rw-r--` | 664 | `www-data` needs write access for SQLite |
| Logs directory | `drwxrwxr-x` | 775 | `www-data` (Apache and cron) appends daily log files |
| Standard directories | `drwxr-xr-x` | 755 | Owner full access, group/world read/execute |
| Standard files | `-rw-r--r--` | 644 | Owner read/write, group/world read |

**Ownership:** application code is owned by the deploy user (`admin`) with group `www-data`; `database/` and `logs/` are owned by `www-data:www-data`. `/var/www/html/sailConnect/sailFriday` is the app root and `public/` is the Apache DocumentRoot.

**One identity for web and cron:** `Connection.php` runs in WAL mode, which creates `sailFriday.db-shm` / `sailFriday.db-wal` sidecar files owned by whichever user opens the database first, and Monolog's `RotatingFileHandler` creates a new `logs/app-*.log` each day owned by whichever process writes first. If cron ran as a different user than Apache, one could create files the other cannot write (`attempt to write a readonly database` / `Permission denied`). To avoid that class of bug entirely, **the cron jobs run as `www-data`** (see [Cron Jobs](#cron-jobs)), so no setgid bit, ACLs or supplementary groups are needed. Do not run `notify.php`, `recalculate.php` or `phinx` as `root` against the live database — if you must, `chown www-data:www-data database/sailFriday.db*` afterwards.

#### Step 8: Verify Environment Configuration

Ensure `.env` file exists with production configuration:

```bash
cat /var/www/html/sailConnect/sailFriday/.env
```

If `.env` doesn't exist or needs updates, see [Environment Configuration](#environment-configuration) section.

**Critical environment variables:**
- `DB_PATH=/var/www/html/sailConnect/sailFriday/database/sailFriday.db`
- `JWT_SECRET` (must be at least 32 characters)
- `APP_DEBUG=false` (never true in production)

#### Step 9: Restart Apache

```bash
sudo systemctl restart apache2
```

#### Step 10: Verify Deployment

Test the API endpoint:

```bash
curl https://sailfriday.sailconnect.org/api/events
```

Expected response:
```json
{
  "success": true,
  "data": {
    "events": [...]
  }
}
```

**Use the Debug Page for System Diagnostics:**

Visit `https://sailfriday.sailconnect.org/app/debug.html` to check:
- Authentication token status
- Current environment name
- API base URL
- Live connection status test

This page is especially useful for troubleshooting authentication issues after deployment.

If you get an error, check Apache logs:
```bash
sudo tail -f /var/log/apache2/sailfriday-error.log
```

#### Step 11: Test Critical Functionality

- [ ] Login works (test at `/app/account.html`)
- [ ] Availability updates work
- [ ] Assignments retrieved correctly
- [ ] Email notifications send (test with admin account)
- [ ] Frontend loads correctly
- [ ] Debug page shows correct system status

---

## Server Layout (Debian, Plain Apache)

Production and dev are both plain **Debian** Lightsail instances with a hand-installed Apache/PHP stack — **not** Bitnami.

| | Value |
|---|---|
| Domains | `sailfriday.sailconnect.org` (sailFriday), `matchrace.sailconnect.org` (matchRace), `auth.sailconnect.org` (single sign-on), `sailconnect.org` (landing) |
| Monorepo root on server | `/var/www/html/sailConnect/` (`sailFriday/`, `matchRace/`, `auth/`) |
| sailFriday app root | `/var/www/html/sailConnect/sailFriday` |
| Apache DocumentRoot | `/var/www/html/sailConnect/sailFriday/public` |
| Web server user/group | `www-data:www-data` (uid 33) |
| Deploy/SSH user | `admin` |
| Apache service | `sudo systemctl restart apache2` |
| Apache logs | `/var/log/apache2/` |
| Cron | `/etc/cron.d/sailfriday-notify`, user column `www-data` (see [Cron Jobs](#cron-jobs)). Install with `sudo apt install cron` if `systemctl status cron` reports the unit is missing. |

---

## Environment Configuration

Production environment variables must be configured in `.env` file.

### Creating Production .env File

SSH into server:

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday
nano .env
```

Add the following configuration:

```bash
# Database
DB_PATH=/var/www/html/sailConnect/sailFriday/database/sailFriday.db

# JWT Authentication (REQUIRED - CHANGE THIS!)
JWT_SECRET=your-production-secret-key-at-least-32-characters-long-must-be-different-from-dev
JWT_EXPIRATION_MINUTES=60

# Mailjet Email API
MJ_APIKEY_PUBLIC=your_mailjet_public_api_key
MJ_APIKEY_PRIVATE=your_mailjet_private_api_key
EMAIL_FROM=noreply@nsc-sdc.ca
EMAIL_FROM_NAME="Nepean Sailing Club - Sail Friday"
ADMIN_NOTIFICATION_EMAIL=nsc-sdc@nsc.ca

# Application
APP_DEBUG=false
APP_ENV=production
APP_TIMEZONE=America/Toronto
APP_URL=https://sailfriday.sailconnect.org

# CORS (adjust for production frontend domain)
CORS_ALLOWED_ORIGINS=https://your-frontend-domain.com
CORS_ALLOWED_HEADERS=Content-Type,Authorization
```

Save and exit (Ctrl+X, Y, Enter).

### Security Considerations

**Critical Security Settings:**

1. **JWT_SECRET**:
   - Must be at least 32 characters
   - Must be different from development secret
   - Use a cryptographically secure random string
   - Generate with: `openssl rand -base64 32`

2. **APP_DEBUG**:
   - Must be `false` in production
   - When `true`, exposes sensitive error details

3. **Database Permissions**:
   - Database file must not be world-readable
   - Use `chmod 664` (owner + group only)
   - Ownership must be `www-data:www-data` for Apache access

4. **File Permissions**:
   - PHP files should be `644` (not executable)
   - Directories should be `755` (standard permissions)
   - Database directory requires `775` for SQLite WAL mode
   - Never use `777` permissions

5. **Environment File**:
   - `.env` should not be in version control
   - Should be readable only by the deploy user and the `www-data` group
   - Use `chmod 640 .env`

6. **Development Dependencies in Production**:
   - Sail Friday includes Phinx (dev dependency) in production for database migrations
   - This is a trade-off: migration capability vs. minimal attack surface
   - **Alternative for maximum security:**
     - Deploy with full dependencies
     - Run migrations
     - Remove dev dependencies: `composer install --no-dev --optimize-autoloader`
     - Note: Future migrations will require re-installing dev dependencies
   - **Future consideration:** Use a dedicated migration deployment pipeline that doesn't require Phinx in production

---

## Database Management

### Running Migrations in Production

Always backup before running migrations:

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday

# 1. Backup database
sudo cp database/sailFriday.db database/sailFriday.backup.$(date +%Y%m%d_%H%M%S).db

# 2. Check migration status
vendor/bin/phinx status --environment=production

# 3. Run migrations
vendor/bin/phinx migrate --environment=production

# 4. Verify
vendor/bin/phinx status --environment=production
```

If a migration fails, see [Rollback Procedures](#rollback-procedures).

### Backup Procedures

#### Automated Backups

Set up a daily backup cron job:

```bash
sudo crontab -u www-data -e
```

Add the following line (runs as `www-data`, which owns `database/`):

```bash
# Daily backup at 2 AM
0 2 * * * /usr/bin/cp /var/www/html/sailConnect/sailFriday/database/sailFriday.db /var/www/html/sailConnect/sailFriday/database/backups/sailFriday.backup.$(date +\%Y\%m\%d).db
```

Create backups directory:

```bash
mkdir -p /var/www/html/sailConnect/sailFriday/database/backups
```

#### Manual Backups

**Before deployment:**
```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday/database
sudo cp sailFriday.db backups/sailFriday.backup.$(date +%Y%m%d_%H%M%S).db
```

**Download backup to local machine:**
```bash
sftp admin@16.52.222.15
cd var/www/html/database
get sailFriday.db
get sailFriday.backup.*
bye
```

#### Database Backup Strategy

**Retention Policy:**
- Daily backups: Keep for 7 days
- Weekly backups: Keep for 4 weeks
- Monthly backups: Keep for 6 months
- Pre-deployment backups: Keep indefinitely

**Cleanup old backups:**
```bash
# Delete backups older than 7 days
find /var/www/html/sailConnect/sailFriday/database/backups -name "sailFriday.backup.*.db" -mtime +7 -delete
```

### Restore Procedures

If you need to restore from a backup:

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday/database

# List available backups
ls -lh sailFriday.backup.*

# Restore from backup (replace YYYYMMDD_HHMMSS with actual backup timestamp)
sudo cp sailFriday.backup.YYYYMMDD_HHMMSS.db sailFriday.db

# Set permissions
sudo chown www-data:www-data sailFriday.db
sudo chmod 664 sailFriday.db

# Restart Apache
sudo systemctl restart apache2
```

### Querying the Production Database

**Read-only queries are safe:**

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday/database

# Query database
sqlite3 sailFriday.db "SELECT * FROM boats LIMIT 5;"
sqlite3 sailFriday.db "SELECT COUNT(*) FROM crews;"
sqlite3 sailFriday.db "SELECT event_id, event_date FROM events ORDER BY event_date;"
```

**Important:** Never run UPDATE/DELETE queries directly on production database. Use migrations instead.

---

## Cron Jobs

Sail Friday supports several automated email notifications via `bin/notify.php`. The crew/boat owner reminders, the assignment reminder, and the boat owner crew list are scheduled in cron; the combined crew list is run manually when needed.

| Notification | Timing | Recipients | Scheduled |
|---|---|---|---|
| **Crew Reminder** | ~24 hours before event start (23–25 h window) | All registered crew members individually | Yes (hourly cron, `--type=reminder`) |
| **Boat Owner Reminder** | ~24 hours before event start (23–25 h window) | Owner of every boat registered (offered berths) for the event, individually | Yes (hourly cron, `--type=reminder`) |
| **Assignment Reminder** | On event day, within the first hour of the blackout window (default 10:00–11:00) | Each crew assigned to a boat in the persisted flotilla, individually | Yes (hourly cron, `--type=assignment-reminder`) |
| **Boat Owner Crew List** | On event day, within the first hour of the blackout window (default 10:00–11:00) | Owner of every boat in the persisted flotilla, individually | Yes (hourly cron, `--type=owner-crew-list`) |
| **Crew List** | On event day, within the first hour of the blackout window (default 10:00–11:00) | Admin (TO) + all boat owners with linked accounts (CC) | No (manual run only) |

The crew reminder and boat owner reminder are both sent by the single `--type=reminder` run; they share the `reminder` idempotency key, so one hourly cron entry covers both.

The boat owner reminder names the boat, reminds the owner it is registered for the event, and asks them to withdraw it before the deadline if it is no longer available.

The assignment reminder names the crew's assigned boat and its owner, tells them to arrive by the event start time, and warns that absence at the start time is flagged as a no-show.

The boat owner crew list emails each owner in the flotilla the display names of the crew assigned to their boat, and closes by looking forward to seeing them at the start time.

Idempotency is enforced via the `cron_notifications` database table (UNIQUE constraint on `event_id + type`), so even if the cron fires multiple times in the same window, the email is only ever sent once.

### Initial Setup (One-Time)

#### 1. Create Logs Directory

```bash
sudo mkdir -p /var/www/html/sailConnect/sailFriday/logs
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/logs
sudo chmod 775 /var/www/html/sailConnect/sailFriday/logs
```

`logs/app-*.log` is appended to by both Apache and cron, and Monolog's `RotatingFileHandler` creates a brand-new file each day — whichever process gets there first sets that day's owner/mode. Because both run as `www-data`, whichever creates the file, the other can write it. (On the previous Bitnami host the two ran as different users, which caused a production incident on 2026-09-04 where `notify.php` sent a partial batch of emails, crashed trying to log the send, and never recorded `cron_notifications`. `bin/log-write-test.php` below can verify logging still works across the next day's rotation.)

#### 2. Set Script Executable (optional)

```bash
chmod +x /var/www/html/sailConnect/sailFriday/bin/notify.php
```

#### 3. Run the Migration

The `cron_notifications` table is created by Phinx migration. If you haven't already run migrations after this feature was deployed:

```bash
cd /var/www/html/sailConnect/sailFriday
vendor/bin/phinx migrate --environment=production
```

#### 4. Configure Crontab

Install cron if needed (`sudo apt install cron`), then create a system cron file whose user column is `www-data`, so cron and Apache share one identity:

```bash
sudo nano /etc/cron.d/sailfriday-notify
```

Add the following lines (run hourly; the script handles its own timing logic). Files in `/etc/cron.d/` must be root-owned, mode 644, and end with a newline:

```bash
# Sail Friday: crew reminder email (~24h before event)
0 * * * * www-data /usr/bin/php /var/www/html/sailConnect/sailFriday/bin/notify.php --type=reminder >> /var/www/html/sailConnect/sailFriday/logs/cron.log 2>&1

# Sail Friday: assignment reminder email (on event day when the blackout window opens)
0 * * * * www-data /usr/bin/php /var/www/html/sailConnect/sailFriday/bin/notify.php --type=assignment-reminder >> /var/www/html/sailConnect/sailFriday/logs/cron.log 2>&1

# Sail Friday: boat owner crew list email (on event day when the blackout window opens)
0 * * * * www-data /usr/bin/php /var/www/html/sailConnect/sailFriday/bin/notify.php --type=owner-crew-list >> /var/www/html/sailConnect/sailFriday/logs/cron.log 2>&1
```

**Note:** The combined crew list email (`--type=crew-list`) is not scheduled in cron. The script still supports it for manual runs (see [Manual Test Run](#manual-test-run)).

**Note:** Verify the PHP path with `which php` if it differs from `/usr/bin/php`.

#### 5. Verify Required Environment Variables

Ensure the following is set in `/var/www/html/sailConnect/sailFriday/.env`:

```bash
ADMIN_NOTIFICATION_EMAIL=your-admin@example.com
```

This is the TO address for the crew list email. All other email settings (SMTP credentials, FROM address) are shared with the regular notification emails.

### How the Timing Windows Work

The script runs hourly but exits early unless conditions are met:

**Reminder (`--type=reminder`):**
- Checks if the next event starts between 23 and 25 hours from now
- This 2-hour window provides tolerance for hourly cron scheduling drift
- Sends both the crew reminder and the boat owner reminder in the same run

**Crew List (`--type=crew-list`), Assignment Reminder (`--type=assignment-reminder`) and Boat Owner Crew List (`--type=owner-crew-list`):**
- Checks if today is the event day
- Checks if the current time falls within `blackout_from` to `blackout_from + 1 hour` (as configured in `season_config`)
- Default window: 10:00–11:00 local time

### Manual Test Run

You can run the script manually to test without waiting for the cron:

```bash
cd /var/www/html/sailConnect/sailFriday

# Dry-run output only — script will exit if outside the timing window
php bin/notify.php --type=reminder
php bin/notify.php --type=assignment-reminder
php bin/notify.php --type=owner-crew-list
php bin/notify.php --type=crew-list
```

To force a send outside the normal window (e.g. for testing), temporarily comment out the timing check in `bin/notify.php`, run it, then revert.

**Important:** Delete the `cron_notifications` row for that event first if you want to re-send:

```bash
sqlite3 /var/www/html/sailConnect/sailFriday/database/sailFriday.db \
  "DELETE FROM cron_notifications WHERE event_id = 'Fri May 29' AND type = 'reminder';"
```

### Monitoring Cron Logs

```bash
# Live log tail
tail -f /var/www/html/sailConnect/sailFriday/logs/cron.log

# Show last 50 lines
tail -50 /var/www/html/sailConnect/sailFriday/logs/cron.log

# Show only sends (filter out early-exit lines)
grep "Done\." /var/www/html/sailConnect/sailFriday/logs/cron.log
```

### Log Write Diagnostic

`bin/log-write-test.php` is a manual ops tool — **not** scheduled by default. It writes one entry through the app's `LoggerInterface` (the same Monolog handler `notify.php`'s use cases log through) and emails the admin notification address with the result. Use it to confirm `logs/app-*.log` is writable without waiting for a real event's notification window to exercise it — e.g. after touching `logs/` permissions, or across a midnight file-rotation boundary.

```bash
php bin/log-write-test.php
```

To run it hourly for a day while validating a permissions fix, add it to crontab and comment it back out (don't delete the file — leave it available for the next time this needs checking):

```bash
0 * * * * /usr/bin/php /var/www/html/sailConnect/sailFriday/bin/log-write-test.php >> /var/www/html/sailConnect/sailFriday/logs/cron.log 2>&1
# 0 * * * * /usr/bin/php /var/www/html/sailConnect/sailFriday/bin/log-write-test.php >> /var/www/html/sailConnect/sailFriday/logs/cron.log 2>&1
```

**Sample log output:**

```
[2026-05-28 10:00:01] notify.php --type=reminder
  Sent reminder to Alice Smith (alice@example.com)
  Sent reminder to Bob Jones (bob@example.com)
Done. sent=2 skipped=0

[2026-05-29 10:00:02] notify.php --type=crew-list
  CC: owner@example.com (owner of Sailaway)
  Crew list sent to admin@nsc-sdc.ca with 1 CC recipient(s)
Done. sent=1 skipped=0
```

### Viewing Notification History

Query the `cron_notifications` table to see what has been sent:

```bash
sqlite3 /var/www/html/sailConnect/sailFriday/database/sailFriday.db \
  "SELECT event_id, type, sent_at, recipients_count, skipped_count FROM cron_notifications ORDER BY sent_at DESC LIMIT 20;"
```

### Log Rotation

Cron logs will grow over time. Set up log rotation:

```bash
sudo nano /etc/logrotate.d/sailfriday-cron
```

```
/var/www/html/sailConnect/sailFriday/logs/cron.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
}
```

---

## Monitoring

### Check Application Logs

**Structured application log** (JSON, written by Sail Friday directly):
```bash
# Today's log
tail -f /var/www/html/sailConnect/sailFriday/logs/app.log

# All rotated files
ls -lh /var/www/html/sailConnect/sailFriday/logs/app-*.log

# Filter by event type
grep '"event":"auth.login"' /var/www/html/sailConnect/sailFriday/logs/app-$(date +%Y-%m-%d).log | jq .
```

Log rotation is handled automatically by Monolog (`RotatingFileHandler`): 30 daily files are kept, named `app-YYYY-MM-DD.log`. No system-level logrotate configuration is needed for this file.

**Apache Error Log** (PHP fatal errors, Apache-level problems):
```bash
sudo tail -f /var/log/apache2/error.log
```

**Apache Access Log:**
```bash
sudo tail -f /var/log/apache2/access.log
```

**Filter Apache log for errors only:**
```bash
sudo grep -i error /var/log/apache2/error.log | tail -20
```

### Check Database Size

```bash
ls -lh /var/www/html/sailConnect/sailFriday/database/sailFriday.db
```

Monitor database growth over time to identify potential issues.

### Health Checks

**API Health Check:**
```bash
curl -s https://sailfriday.sailconnect.org/api/events | jq '.success'
```

Expected output: `true`

**Database Connection Check:**
```bash
curl -s https://sailfriday.sailconnect.org/api/events | grep -o '"success":[^,]*'
```

### Performance Monitoring

**Check Apache Process Count:**
```bash
ps aux | grep httpd | wc -l
```

**Check Memory Usage:**
```bash
free -h
```

**Check Disk Space:**
```bash
df -h /var/www/html/sailConnect/sailFriday
```

### Setting Up Alerts

Create a monitoring script that sends alerts when issues are detected:

**File:** `/home/admin/monitor.sh`

```bash
#!/bin/bash

# Check if API is responding
API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://sailfriday.sailconnect.org/api/events)

if [ "$API_STATUS" != "200" ]; then
    echo "API is down! Status: $API_STATUS" | mail -s "Sail Friday API Alert" admin@example.com
fi

# Check disk space
DISK_USAGE=$(df -h /var/www/html/sailConnect/sailFriday | awk 'NR==2 {print $5}' | sed 's/%//')

if [ "$DISK_USAGE" -gt 80 ]; then
    echo "Disk usage is at ${DISK_USAGE}%!" | mail -s "Sail Friday Disk Alert" admin@example.com
fi
```

Add to cron (run every 15 minutes):
```bash
*/15 * * * * /home/admin/monitor.sh
```

### Shipping Logs to CloudWatch Logs (Optional)

Forwarding `logs/app.log` to AWS CloudWatch Logs gives you searchable, alertable structured logs in the AWS Console with long-term retention.

#### 1. Install the CloudWatch Agent

```bash
wget https://s3.amazonaws.com/amazoncloudwatch-agent/debian/amd64/latest/amazon-cloudwatch-agent.deb
sudo dpkg -i amazon-cloudwatch-agent.deb
```

#### 2. Attach an IAM Role to the Lightsail Instance

The agent needs `logs:CreateLogGroup`, `logs:CreateLogStream`, and `logs:PutLogEvents` permissions. The easiest approach is to attach an IAM role with the `CloudWatchAgentServerPolicy` managed policy to your Lightsail instance. See the [AWS docs](https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/create-iam-roles-for-cloudwatch-agent.html) for details.

#### 3. Configure the Agent

Create the config file:

```bash
sudo nano /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json
```

```json
{
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {
            "file_path": "/var/www/html/sailConnect/sailFriday/logs/app-*.log",
            "log_group_name": "sailfriday/application",
            "log_stream_name": "{instance_id}",
            "timestamp_format": "%Y-%m-%dT%H:%M:%S",
            "multi_line_start_pattern": "^\\{"
          }
        ]
      }
    }
  }
}
```

The wildcard `app-*.log` picks up all rotated daily files automatically.

#### 4. Start the Agent

```bash
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
  -a fetch-config \
  -m ec2 \
  -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json \
  -s
```

Verify it is running:
```bash
sudo systemctl status amazon-cloudwatch-agent
```

#### 5. Query Logs in CloudWatch

In the AWS Console → CloudWatch → Log Insights, select log group `sailfriday/application`:

```
# All errors in the last hour
fields @timestamp, level, message, event
| filter level = "ERROR"
| sort @timestamp desc
| limit 50

# Failed logins
fields @timestamp, context.email, context.ip
| filter event = "auth.login_failed"
| sort @timestamp desc
```

---

## Rollback Procedures

If deployment fails or introduces critical bugs, follow these steps to rollback.

### Rollback Checklist

- [ ] Identify the issue (check logs, test endpoints)
- [ ] Determine rollback scope (code only, or code + database)
- [ ] Notify team/users if downtime is required
- [ ] Execute rollback procedures
- [ ] Verify system is working
- [ ] Document what went wrong

### Code Rollback

If the issue is in the code (not database):

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday

# Option 1: Checkout previous git commit (if using git on server)
git checkout <previous-commit-hash>

# Option 2: Re-upload previous version via SFTP
# (Upload previous src/, config/, public/index.php from local backup)

# Reinstall dependencies
composer install --no-dev --optimize-autoloader

# Restart Apache
sudo systemctl restart apache2
```

### Database Rollback

If a migration caused issues:

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday

# Option 1: Rollback last migration via Phinx
vendor/bin/phinx rollback --environment=production

# Option 2: Restore from backup
cd database
sudo cp sailFriday.backup.YYYYMMDD_HHMMSS.db sailFriday.db
sudo chown www-data:www-data sailFriday.db
sudo chmod 664 sailFriday.db

# Restart Apache
sudo systemctl restart apache2
```

### Full System Rollback

If both code and database need to be rolled back:

```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday

# 1. Restore database
sudo cp database/sailFriday.backup.YYYYMMDD_HHMMSS.db database/sailFriday.db
sudo chown www-data:www-data database/sailFriday.db
sudo chmod 664 database/sailFriday.db

# 2. Restore code (via git or SFTP)
git checkout <previous-commit-hash>
# OR re-upload via SFTP

# 3. Reinstall dependencies
composer install --no-dev --optimize-autoloader

# 4. Restart Apache
sudo systemctl restart apache2

# 5. Verify
curl https://sailfriday.sailconnect.org/api/events
```

### Post-Rollback

After rolling back:

1. **Verify** system is working correctly
2. **Document** what went wrong in deployment notes
3. **Fix** the issue in development environment
4. **Test** thoroughly before next deployment
5. **Update** deployment procedures if needed

---

## Troubleshooting

### Common Production Issues

#### Issue: "500 Internal Server Error"

**Possible Causes:**
- PHP syntax error
- Missing dependencies
- Database connection error
- File permission issues

**Solution:**

1. Check Apache error log:
   ```bash
   sudo tail -50 /var/log/apache2/error.log
   ```

2. Look for specific error message (syntax errors, file not found, etc.)

3. Verify file permissions:
   ```bash
   ls -la /var/www/html/sailConnect/sailFriday/src
   ls -la /var/www/html/sailConnect/sailFriday/database
   ```

4. Verify dependencies installed:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

#### Issue: "Database locked" error

**Cause:** Multiple processes trying to write to SQLite simultaneously

**Solution:**

1. Check for open connections:
   ```bash
   fuser /var/www/html/sailConnect/sailFriday/database/sailFriday.db
   ```

2. Restart Apache to clear connections:
   ```bash
   sudo systemctl restart apache2
   ```

3. If problem persists, verify WAL mode is enabled:
   ```bash
   sqlite3 /var/www/html/sailConnect/sailFriday/database/sailFriday.db "PRAGMA journal_mode;"
   ```
   Should return: `wal`

#### Issue: "Permission denied" when accessing database, or `attempt to write a readonly database`

**Cause:** Incorrect file permissions, or — if `sailFriday.db` already looks correct — a process running as a user other than `www-data` (e.g. a cron job or `phinx` run as `root`/`admin`) briefly created `sailFriday.db-wal`/`sailFriday.db-shm` that Apache cannot write. The sidecar files may be gone by the time you check with `ls`. See [Step 7](#step-7-set-final-file-permissions).

**Solution:**

```bash
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/database/sailFriday.db
sudo chmod 664 /var/www/html/sailConnect/sailFriday/database/sailFriday.db
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/database
sudo chmod 775 /var/www/html/sailConnect/sailFriday/database
cat /etc/cron.d/sailfriday-notify   # user column must be www-data
```

#### Issue: "JWT token invalid" after deployment

**Cause:** JWT_SECRET changed or token format changed

**Solution:**

1. Verify `JWT_SECRET` in `.env` hasn't changed
2. If it changed, all users need to login again to get new tokens
3. Clear any cached tokens on frontend

#### Issue: "Authentication not working after deployment"

**Cause:** Authorization header not being forwarded to PHP, or JWT configuration issues

**Solution:**

1. Visit `https://sailfriday.sailconnect.org/app/debug.html` to diagnose the issue
2. Check **"Token Present"** status - should show if authentication token exists
3. Check **"API Connection"** status - tests live connectivity to the API
4. Verify Authorization header forwarding in `.htaccess`:
   ```apache
   RewriteCond %{HTTP:Authorization} .
   RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
   ```
5. If the header forwarding rule is missing, JWT authentication will fail silently
6. Ensure `AllowOverride All` is set in the Apache virtual host configuration
7. Restart Apache after any `.htaccess` changes:
   ```bash
   sudo systemctl restart apache2
   ```

#### Issue: Email notifications not sending

**Possible Causes:**

- Mailjet API credentials missing or incorrect
- Sender domain/address not verified in Mailjet
- Mailjet account is in sandbox mode (outbound blocked)

**Solution:**

1. Check Mailjet credentials in `.env`:
   ```bash
   cat /var/www/html/sailConnect/sailFriday/.env | grep MJ_
   ```

2. Verify the keys are valid by logging into [app.mailjet.com](https://app.mailjet.com) → Account Settings → API Key Management.

3. Check Apache error log for Mailjet response status codes:
   ```bash
   tail -f /var/log/apache2/error.log | grep -i "email send"
   ```
   A non-2xx status (e.g. 401 = bad credentials, 403 = domain not verified) will be logged.

4. Ensure the sender address (`EMAIL_FROM`) is a verified sender in your Mailjet account under **Sender domains & addresses**.

5. Confirm the Mailjet account is not in sandbox/test mode, which blocks real outbound delivery.

#### Issue: Frontend not loading

**Possible Causes:**
- Apache configuration incorrect
- .htaccess not working
- File permissions

**Solution:**

1. Verify Apache virtual host configuration:
   ```bash
   sudo cat /etc/apache2/sites-available/sailfriday.conf
   ```

2. Verify .htaccess exists:
   ```bash
   cat /var/www/html/sailConnect/sailFriday/public/.htaccess
   ```

3. Test Apache rewrite module:
   ```bash
   sudo apachectl -M | grep rewrite
   ```
   Should see: `rewrite_module (shared)`

4. Restart Apache:
   ```bash
   sudo systemctl restart apache2
   ```

---

## Next Steps

Now that you understand Sail Friday deployment:

✅ Deployment complete!
➡️ **Next:** Set up [monitoring alerts](#setting-up-alerts) for proactive issue detection

✅ Production running!
➡️ **Next:** Review [Database Management](../database/README.md) for ongoing maintenance

✅ Rollback plan ready!
➡️ **Next:** Document your deployment process in team wiki

---

📖 **Additional Resources:**

- [Setup Guide](SETUP.md) - Local development setup
- [Database README](../database/README.md) - Database management
- [API Reference](API.md) - API endpoint documentation
- [Developer Guide](DEVELOPER_GUIDE.md) - Architecture and patterns
