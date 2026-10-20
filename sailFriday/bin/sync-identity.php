#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Copies email address and membership changes made in sailConnect (auth) into Sail Friday for every
 * linked user, so cron notifications and rankings do not wait for the user's next visit.
 *
 * Usage:   php bin/sync-identity.php
 * Cron:    *\/15 * * * * php /path/to/sailFriday/bin/sync-identity.php >> logs/cron.log 2>&1
 */

use App\Application\UseCase\Auth\SyncIdentityUseCase;
use App\Infrastructure\Persistence\SQLite\CrewRepository;
use App\Infrastructure\Persistence\SQLite\UserRepository;
use SailConnect\Auth\Config;
use SailConnect\Auth\Database;

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

if (file_exists($projectRoot . '/.env')) {
    Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
}
$db = getenv('DB_PATH') ?: ($_ENV['DB_PATH'] ?? '');
putenv('DB_PATH=' . ($db !== '' && str_starts_with($db, '/') ? $db : $projectRoot . '/database/sailFriday.db'));

$authDir = getenv('AUTH_DIR') ?: dirname($projectRoot) . '/auth';
$auth = Database::connect(Config::load($authDir)->dbPath(), true);

$users = new UserRepository();
$sync = new SyncIdentityUseCase($users, new CrewRepository());

$changed = 0;
foreach ($auth->query('SELECT user_id, email_address, is_member, membership_number FROM users WHERE is_platform_active = 1') as $identity) {
    $user = $users->findByAuthUserId($identity['user_id']);
    if ($user !== null && $sync->execute($user, $identity)) {
        $changed++;
    }
}
echo date('c') . " sync-identity: {$changed} user(s) updated\n";
