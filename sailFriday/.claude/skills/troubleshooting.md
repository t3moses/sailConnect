# Troubleshooting Skill

Common issues and solutions for Sail Friday development.

## Database Issues

### Database Locked Error

**Symptom:** `database is locked` error when running queries

**Causes:**
- Another process has an open connection
- Uncommitted transaction
- Dev server holding connection

**Solutions:**

**Windows PowerShell:**
```powershell
# Stop process using local API port (common DB lock source)
Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue |
   ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }

# Then start fresh
php -S localhost:8000 -t public
```

**Mac/Linux:**
```bash
# Check for active connections
lsof database/sailFriday.db

# Force close (be careful!)
fuser -k database/sailFriday.db

# Restart dev server
# Kill existing server first
lsof -ti:8000 | xargs kill -9
# Then start fresh
php -S localhost:8000 -t public
```

**Prevention:**
- Close DB connections in finally blocks
- Use WAL mode (already enabled in Connection.php)
- Don't leave transactions uncommitted

---

### Database Permission Errors

**Symptom:** Cannot write to database / Permission denied, or `PDOException: SQLSTATE[HY000]: General error: 8 attempt to write a readonly database`

**Development:**
```bash
chmod 664 database/sailFriday.db
chmod 775 database/
```

**Production:**
```bash
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/database/sailFriday.db
sudo chmod 664 /var/www/html/sailConnect/sailFriday/database/sailFriday.db
sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/database
sudo chmod 775 /var/www/html/sailConnect/sailFriday/database
```

**Recurring root cause to check first:** if `sailFriday.db` already shows correct ownership (`www-data:www-data`, mode `664`) but writes still fail intermittently, check which user the cron jobs run as. Apache and cron must share one identity (`www-data`). `Connection.php` runs in WAL mode, which creates `sailFriday.db-shm` / `sailFriday.db-wal` sidecar files owned by whichever user opens the database first; a cron job running as `root` or `admin` can leave sidecars `www-data` cannot write, producing `attempt to write a readonly database`. They are short-lived, so you generally won't catch them with a plain `ls -la`. Confirm with:
```bash
grep "readonly database" /var/www/html/sailConnect/sailFriday/logs/app-*.log   # look for clusters near :00 cron ticks
cat /etc/cron.d/sailfriday-notify                             # user column must be www-data
```
Fix: make the cron user `www-data` (see `docs/DEPLOYMENT.md` Cron Jobs), then `sudo chown www-data:www-data /var/www/html/sailConnect/sailFriday/database/sailFriday.db*`.

---

### Migration Fails

**Symptom:** Migration throws errors

**Solutions:**

**Check migration status:**
```bash
vendor/bin/phinx status
```

**Rollback and retry:**
```bash
vendor/bin/phinx rollback
vendor/bin/phinx migrate
```

**Manual fix:**
```bash
# Check what went wrong
sqlite3 database/sailFriday.db ".schema table_name"

# If needed, manually fix
sqlite3 database/sailFriday.db "DROP TABLE IF EXISTS problem_table;"
```

**For complex schema changes:**
- Use create-copy-drop pattern (SQLite limitation)
- See `/modify-schema` skill

---

### Corrupted Database

**Symptom:** Database queries fail unexpectedly

**Check integrity:**
```bash
sqlite3 database/sailFriday.db "PRAGMA integrity_check;"
```

**If corrupted, dump and restore:**
```bash
sqlite3 database/sailFriday.db ".dump" | sqlite3 database/sailFriday.new.db
mv database/sailFriday.db database/sailFriday.corrupted.db
mv database/sailFriday.new.db database/sailFriday.db
```

---

## Test Failures

### Unit Tests Failing

**Symptom:** Tests that should pass are failing

**Common causes:**

**1. Deterministic behavior broken:**
```bash
# Check if selection/assignment algorithms produce consistent results
./vendor/bin/phpunit tests/Unit/Domain/Service/SelectionServiceTest.php
```

**2. Dependencies not installed:**
```bash
composer install
```

**3. PHP version mismatch:**
```bash
php -v  # Should be 8.1+
```

---

### Integration Tests Failing

**Symptom:** Tests fail with database errors

**Solutions:**

**Check Phinx migrations work:**
```bash
# Integration tests use in-memory DB with Phinx
# Verify migrations run cleanly
rm -f database/test.db
vendor/bin/phinx migrate -e development
```

**Base class not extended:**
- Integration tests MUST extend `IntegrationTestCase`
- This provides automatic Phinx migration setup

**Missing test data:**
- Use base class utilities: `createTestEvent()`, `createTestUser()`

---

### API Tests Failing

**Symptom:** API test suite fails

**Check dev server:**
```powershell
# PowerShell
Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue

# If not running, start it
$p = Start-Process php -ArgumentList '-S','localhost:8000','-t','public' -PassThru
Start-Sleep -Seconds 2
```

Or on Mac/Linux:
```bash
# API tests need dev server on port 8000
lsof -i :8000

# If not running, start it
php -S localhost:8000 -t public &
```

**Check JWT_SECRET:**
```powershell
# PowerShell
$env:JWT_SECRET

# If missing, set it
$env:JWT_SECRET = "your-test-secret-min-32-chars"
```

Or on Mac/Linux:
```bash
# API tests need valid JWT_SECRET
echo $JWT_SECRET

# If missing, set it
export JWT_SECRET="your-test-secret-min-32-chars"
```

**Database seeded:**
```bash
vendor/bin/phinx seed:run
```

---

## Apache/Server Issues

### Apache Won't Start

**Check logs:**
```bash
sudo tail -50 /var/log/apache2/error.log
```

**Check config:**
```bash
sudo apachectl configtest
```

**Restart:**
```bash
sudo systemctl restart apache2
```

---

### 404 on All API Endpoints

**Symptom:** All `/api/*` routes return 404

**Causes:**
- `.htaccess` not working
- `mod_rewrite` not enabled
- Wrong document root

**Solutions:**

**Check .htaccess exists:**
```bash
ls -la public/.htaccess
```

**Verify mod_rewrite:**
```bash
apache2ctl -M | grep rewrite
```

**Check document root:**
- Should point to `/path/to/sailFriday/public/`
- NOT `/path/to/sailFriday/`

---

### 500 Internal Server Error

**Check structured app log first** (JSON, most informative):
```bash
# Development
tail -f logs/app.log

# Production
ssh admin@16.52.222.15 'tail -f /var/www/html/sailConnect/sailFriday/logs/app-$(date +%Y-%m-%d).log'
```

**Then check PHP/Apache output** (PHP fatals, server-level errors):
```bash
# Development (php -S): errors print to the terminal running the dev server
# No log file — check the terminal window where you ran:
#   php -S localhost:8000 -t public

# Production (Apache)
ssh admin@16.52.222.15 'sudo tail -f /var/log/apache2/error.log'
```

**Common causes:**
- PHP syntax error (check terminal / Apache log)
- Missing dependency (run `composer install`)
- Database connection error (check permissions)
- Missing .env file (check environment variables)

---

## Composer Issues

### Composer Install Fails

**Clear cache:**
```bash
composer clear-cache
```

**Update composer:**
```bash
composer self-update
```

**Verbose output:**
```bash
composer install -vvv
```

**Memory limit:**
```bash
php -d memory_limit=-1 /usr/bin/composer install
```

---

## Git Issues

### Pre-commit Hook Fails

**Symptom:** Commit blocked by hook

**Don't skip hooks (unless explicitly approved):**
```bash
# DON'T DO THIS without understanding why hook failed
git commit --no-verify
```

**Instead, fix the issue:**
1. Check what hook failed
2. Fix the underlying problem
3. Stage fixes
4. Commit normally

**Common causes:**
- Linting errors (fix code style)
- Test failures (fix failing tests)
- Security issues (fix vulnerabilities)

---

### Merge Conflicts

**Check conflicts:**
```bash
git status
```

**Resolve conflicts:**
1. Open conflicted files
2. Look for `<<<<<<<`, `=======`, `>>>>>>>`
3. Edit to keep desired changes
4. Remove conflict markers
5. Stage resolved files: `git add <file>`
6. Continue: `git merge --continue` or `git rebase --continue`

**Abort if needed:**
```bash
git merge --abort
# or
git rebase --abort
```

---

## PHP Issues

### Class Not Found

**Symptom:** `Class 'App\...' not found`

**Solutions:**

**Regenerate autoload:**
```bash
composer dump-autoload
```

**Check namespace:**
- Namespace should match directory structure
- `App\Domain\Entity\Boat` → `src/Domain/Entity/Boat.php`

**Check class name:**
- Class name must match filename
- Case-sensitive on Linux

---

### Memory Exhausted

**Symptom:** `Fatal error: Allowed memory size exhausted`

**Increase memory limit:**
```bash
# Temporary (command-line)
php -d memory_limit=512M script.php

# PHP.ini (permanent)
memory_limit = 512M
```

**Optimize code:**
- Process large datasets in chunks
- Use generators for large result sets
- Unset large variables when done

---

## Authentication Issues

### JWT Token Invalid

**Symptom:** 401 Unauthorized on authenticated endpoints

**Check JWT_SECRET:**
```bash
# Must be same secret used to generate token
echo $JWT_SECRET
```

**Check token expiration:**
- Default: 60 minutes
- Token may have expired
- Login again to get fresh token

**Check header format:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/users/me
```

---

### User Not Found

**Symptom:** `UserNotFoundException` on login

**Check if user exists:**
```bash
sqlite3 database/sailFriday.db "SELECT * FROM users WHERE email = 'user@example.com';"
```

**Register user:**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

---

## Performance Issues

### Slow Queries

**Enable query logging:**
- Add logging to `Connection.php`
- Use SQLite `EXPLAIN QUERY PLAN`

**Check indexes:**
```bash
sqlite3 database/sailFriday.db "PRAGMA index_list(table_name);"
```

**Optimize:**
- Add indexes on frequently queried columns
- Use `EXPLAIN QUERY PLAN` to analyze
- Consider denormalization for read-heavy queries

---

### Slow Tests

**Symptom:** Test suite takes too long

**Solutions:**

**Run only what you need:**
```bash
# Unit tests only (fastest)
./vendor/bin/phpunit tests/Unit

# Specific test
./vendor/bin/phpunit tests/Unit/Domain/Service/SelectionServiceTest.php
```

**Parallel testing:**
```bash
# Not currently implemented, but could use paratest
composer require --dev brianium/paratest
vendor/bin/paratest
```

---

## CI/CD Issues

### GitHub Actions Failing

**Check logs:**
- Go to Actions tab in GitHub
- Click on failed workflow
- Review job logs

**Common issues:**

**Unit tests fail on push:**
- Fix unit tests locally first
- Run `./vendor/bin/phpunit tests/Unit`

**Integration tests fail on PR:**
- Check database setup job
- Verify Phinx migrations work

**API tests fail on PR:**
- Check if dev server starts correctly
- Verify seeds run successfully

**PHPStan job fails on PR:**
- Run locally: `vendor/bin/phpstan analyse`
- Fix type errors or missing return types
- Do NOT suppress with baseline — fix the underlying issue
- If the error is in a third-party or legacy file, check if it's already excluded in `phpstan.neon`

**ESLint job fails:**
- Run locally: `npm run lint`
- Auto-fix where possible: `npm run lint:fix`
- Fix remaining errors manually (usually undefined variables or unused imports)

---

## Production Issues

### Deployment Failed

**Check deployment log:**
- Review SFTP upload errors
- Check SSH connection
- Verify file permissions

**Rollback:**
```bash
ssh admin@16.52.222.15
cd /var/www/html/sailConnect/sailFriday
git reset --hard <previous-commit-hash>
composer install --no-dev
sudo systemctl restart apache2
```

**For database rollback:**
```bash
cd /var/www/html/sailConnect/sailFriday/database
cp sailFriday.backup.YYYYMMDD_HHMMSS.db sailFriday.db
```

---

### Production Data Issue

**NEVER directly modify production database unless:**
1. You have a backup
2. You've tested the change locally
3. You understand the impact

**Safe procedure:**
1. Backup production DB
2. Download backup to local
3. Test fix on local copy
4. Create migration if schema change
5. Deploy migration properly

---

## Getting Help

If none of these solutions work:

1. **Check logs:**
   - Development: `logs/app.log` (structured JSON) → browser console → PHP/Apache errors
   - Production: `/var/www/html/sailConnect/sailFriday/logs/app-YYYY-MM-DD.log` → Apache error log

2. **Search the codebase:**
   - Use Grep skill: `/database-ops`
   - Check similar implementations

3. **Review documentation:**
   - `docs/DEVELOPER_GUIDE.md`
   - `CLAUDE.md`
   - Skills in `.claude/skills/`

4. **Ask for help:**
   - Provide error messages
   - Show what you've tried
   - Include relevant logs

---

## Prevention Tips

**Development:**
- Run tests before committing
- Use proper error handling
- Check logs regularly
- Keep dependencies updated

**Database:**
- Always backup before migrations
- Test migrations locally first
- Use Phinx for all schema changes
- Never bypass migrations

**Git:**
- Commit often with good messages
- Don't skip pre-commit hooks
- Resolve conflicts carefully
- Keep branches up to date

**Production:**
- Test in development first
- Have rollback plan ready
- Monitor logs after deployment
- Keep backups current
