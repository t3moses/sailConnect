# Deploy to AWS Lightsail Skill

Step-by-step deployment guide for the Sail Friday application to AWS Lightsail production environment.

Primary deployment path is the GitHub Actions workflow in `.github/workflows/deploy.yml` (`workflow_dispatch`). Manual SSH/SFTP steps below are fallback procedures.

**Note:** See [docs/DEPLOYMENT.md](../../docs/DEPLOYMENT.md) for complete deployment documentation.

## Prerequisites

- SSH key configured: `LightsailDefaultKey-ca-central-1.pem`
- Production server: `admin@16.54.93.63`
- Local changes committed and tested
- All tests passing locally

## Deployment Steps

### 0. Preferred: Run Deployment Workflow

Use GitHub Actions `Deploy to AWS Lightsail` workflow with inputs:
- `environment` (`staging` or `production`)
- `run_migrations` (as needed)
- `skip_backup` (normally `false`)

This path includes pre-deployment checks and API test gating.

### 1. Upload Files via SFTP

```bash
# Add SSH key to agent
ssh-add LightsailDefaultKey-ca-central-1.pem

# Start SFTP session
sftp admin@16.54.93.63

# Navigate to application directory
cd /var/www/html/sailConnect/sailFriday

# Upload directories
put -r public
put -r src
put -r config

# Upload specific files
put composer.json
put composer.lock
put phinx.php

# Upload migrations
put -r database/migrations

# Exit SFTP
bye
```

### 2. Set Permissions and Install Dependencies

```bash
# SSH into server
ssh admin@16.54.93.63

# Navigate to application directory
cd /var/www/html/sailConnect/sailFriday

# Set ownership
sudo chown -R www-data:www-data /var/www/html/sailConnect/sailFriday

# Install dependencies (production mode)
composer install --optimize-autoloader --no-dev

# Set database permissions (cron runs as www-data too, so plain 775 is
# enough - see docs/DEPLOYMENT.md Step 7)
sudo chmod 775 database
sudo chmod 664 database/sailFriday.db

# Restart Apache
sudo systemctl restart apache2
```

### 3. Run Database Migrations (if needed)

```bash
# Still connected via SSH
cd /var/www/html/sailConnect/sailFriday

# Backup database first!
cp database/sailFriday.db database/sailFriday.backup.$(date +%Y%m%d_%H%M%S).db

# Run migrations
vendor/bin/phinx migrate -e production

# Verify
vendor/bin/phinx status -e production
```

### 4. Verify Deployment

```bash
# Check Apache status
sudo systemctl status apache2

# Check logs for errors
sudo tail -f /var/log/apache2/error.log

# Test API endpoint
curl https://sailfriday.sailconnect.org/api/events

# Exit SSH
exit
```

## Quick Deploy Script

For routine deployments without database changes:

```bash
#!/bin/bash
set -e

echo "🚀 Starting Sail Friday deployment..."

# Upload files
ssh-add LightsailDefaultKey-ca-central-1.pem
sftp admin@16.54.93.63 <<EOF
cd /var/www/html/sailConnect/sailFriday
put -r public
put -r src
put -r config
put composer.json
put composer.lock
bye
EOF

# Set permissions and restart
ssh admin@16.54.93.63 <<'EOF'
cd /var/www/html/sailConnect/sailFriday
sudo chown -R www-data:www-data /var/www/html/sailConnect/sailFriday
composer install --optimize-autoloader --no-dev
sudo systemctl restart apache2
EOF

echo "✅ Deployment complete!"
```

## Pre-Deployment Checklist

- [ ] All tests passing (`./vendor/bin/phpunit`)
- [ ] Changes committed to Git
- [ ] Environment variables configured on server (`.env`)
- [ ] Database backup strategy confirmed
- [ ] No breaking API changes (or clients notified)
- [ ] Migration tested locally (if database changes)
- [ ] Prefer workflow run over manual upload when possible

## Post-Deployment Checklist

- [ ] API endpoints responding (`curl https://sailfriday.sailconnect.org/api/events`)
- [ ] Frontend loading correctly
- [ ] Authentication working
- [ ] Database migrations applied successfully
- [ ] No errors in Apache logs
- [ ] Monitor for 10-15 minutes after deployment

## Rollback Procedure

If deployment fails:

### 1. Restore Database (if migrations were run)

```bash
ssh admin@16.54.93.63
cd /var/www/html/sailConnect/sailFriday/database
cp sailFriday.backup.YYYYMMDD_HHMMSS.db sailFriday.db
```

### 2. Revert Code (via Git)

```bash
ssh admin@16.54.93.63
cd /var/www/html/sailConnect/sailFriday
git reset --hard <previous-commit-hash>
composer install --no-dev
sudo systemctl restart apache2
```

## Common Issues

### Permission Denied

If this recurs after applying the fix, check that the `notify.php`/`recalculate.php` cron jobs run as `www-data` (see `/etc/cron.d/sailfriday-notify` in `docs/DEPLOYMENT.md` Cron Jobs). A job running as another user (e.g. root or `admin`) can leave WAL/SHM sidecar files or log files that `www-data` cannot write — see `docs/DEPLOYMENT.md` Step 7 and `.claude/skills/troubleshooting.md`.

```bash
sudo chown -R www-data:www-data /var/www/html/sailConnect/sailFriday
sudo chmod 775 database
sudo chmod 664 database/sailFriday.db
```

### Apache Won't Start

```bash
# Check logs
sudo tail -50 /var/log/apache2/error.log

# Check Apache config
sudo apachectl configtest

# Restart
sudo systemctl restart apache2
```

### Database Connection Failed

```bash
# Verify database file exists
ls -la /var/www/html/sailConnect/sailFriday/database/sailFriday.db

# Check permissions
ls -la /var/www/html/sailConnect/sailFriday/database/

# Verify .env configuration
cat /var/www/html/sailConnect/sailFriday/.env | grep DB_PATH
```

### Composer Install Fails

```bash
# Clear composer cache
composer clear-cache

# Install with verbose output
composer install --optimize-autoloader --no-dev -vvv
```

## Environment Configuration

Ensure these environment variables are set in `/var/www/html/sailConnect/sailFriday/.env`:

```bash
APP_ENV=production
APP_DEBUG=false
DB_PATH=/var/www/html/sailConnect/sailFriday/database/sailFriday.db
JWT_SECRET=<production-secret>
SMTP_HOST=email-smtp.ca-central-1.amazonaws.com
SMTP_USERNAME=<aws-smtp-username>
SMTP_PASSWORD=<aws-smtp-password>
EMAIL_FROM=noreply@nsc-sdc.ca
CORS_ALLOWED_ORIGINS=https://sailfriday.sailconnect.org
```

## Monitoring

### Check Application Health

```bash
# API health check
curl -I https://sailfriday.sailconnect.org/api/events

# Check response time
time curl https://sailfriday.sailconnect.org/api/events
```

### Monitor Logs

```bash
# Real-time error log
ssh admin@16.54.93.63 'sudo tail -f /var/log/apache2/error.log'

# Real-time access log
ssh admin@16.54.93.63 'sudo tail -f /var/log/apache2/access.log'
```

## Security Notes

- Never commit `.env` file with production secrets
- Keep SSH key secure (`LightsailDefaultKey-ca-central-1.pem`)
- Use `--no-dev` flag in production to exclude dev dependencies
- Always use HTTPS in production
- Regularly update dependencies for security patches
- Monitor logs for suspicious activity

## Additional Resources

- **Full Deployment Guide:** `docs/DEPLOYMENT.md`
- **Server Access:** Contact team lead for SSH key
- **AWS Console:** https://lightsail.aws.amazon.com/
- **Production URL:** https://sailfriday.sailconnect.org
