#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Remove every trace of a user (crew and/or boat owner) from all sailConnect databases. For test accounts only.
 *
 * The user is found by email address in the auth database. Deleted, in this order:
 *   1. sailFriday.db: the user's boats (with availability and history), crew profile (with availability, whitelist,
 *      history and no-shows) and the users row
 *   2. matchRace.db:  the users row (skipped when the file does not exist)
 *   3. auth:          the identity (its sessions and tokens cascade) and its login_attempts
 *
 * Not touched: generated flotillas (JSON per past event) and cron notification counts, which may still mention the crew key.
 * A platform admin is never removed. The three databases cannot share one transaction, so a failure part-way leaves earlier steps done;
 * re-running is safe.
 *
 * Usage:
 *   php bin/remove-test-user.php --email=test@example.org           # dry run: report only
 *   php bin/remove-test-user.php --email=test@example.org --apply   # delete
 *   Options: --db=/path/sailFriday.db  --match-race-db=/path/matchRace.db  --auth-dir=/path/to/auth
 *
 * Back up the databases before using --apply.
 */

use SailConnect\Auth\Config;
use SailConnect\Auth\Database;

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

$opt = getopt('', ['email:', 'apply', 'db:', 'match-race-db:', 'auth-dir:']);
$email = strtolower(trim((string)($opt['email'] ?? '')));
if ($email === '') {
    fwrite(STDERR, "Usage: php bin/remove-test-user.php --email=user@example.org [--apply]\n");
    exit(2);
}
$apply = isset($opt['apply']);
$dbPath = $opt['db'] ?? $projectRoot . '/database/sailFriday.db';
$matchRacePath = $opt['match-race-db'] ?? dirname($projectRoot) . '/matchRace/database/matchRace.db';
$authDir = $opt['auth-dir'] ?? dirname($projectRoot) . '/auth';

$open = static function (string $path): PDO {
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON');   // the child rows are removed by ON DELETE CASCADE
    return $pdo;
};
$count = static function (PDO $pdo, string $sql, array $args): int {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return (int)$st->fetchColumn();
};

$authDb = Database::connect(Config::load($authDir)->dbPath());
$st = $authDb->prepare('SELECT user_id, is_platform_admin FROM users WHERE email_address = ?');
$st->execute([$email]);
$identity = $st->fetch();
if ($identity === false) {
    fwrite(STDERR, "No sailConnect account with email {$email}.\n");
    exit(1);
}
if ((int)$identity['is_platform_admin'] === 1) {
    fwrite(STDERR, "{$email} is a platform admin; refusing to remove.\n");
    exit(1);
}
$userId = $identity['user_id'];

$sf = $open($dbPath);
$report = [
    'sailFriday crews' => $count($sf, 'SELECT COUNT(*) FROM crews WHERE user_id = ?', [$userId]),
    'sailFriday boats' => $count($sf, 'SELECT COUNT(*) FROM boats WHERE owner_user_id = ?', [$userId]),
    'sailFriday users' => $count($sf, 'SELECT COUNT(*) FROM users WHERE auth_user_id = ?', [$userId]),
    'sailFriday crew_availability' => $count($sf, 'SELECT COUNT(*) FROM crew_availability WHERE crew_id IN (SELECT id FROM crews WHERE user_id = ?)', [$userId]),
    'sailFriday crew_whitelist' => $count($sf, 'SELECT COUNT(*) FROM crew_whitelist WHERE crew_id IN (SELECT id FROM crews WHERE user_id = ?)', [$userId]),
    'sailFriday crew_history' => $count($sf, 'SELECT COUNT(*) FROM crew_history WHERE crew_id IN (SELECT id FROM crews WHERE user_id = ?)', [$userId]),
    'sailFriday no_shows' => $count($sf, 'SELECT COUNT(*) FROM no_shows WHERE crew_id IN (SELECT id FROM crews WHERE user_id = ?)', [$userId]),
];
$mr = is_file($matchRacePath) ? $open($matchRacePath) : null;
$report['matchRace users'] = $mr ? $count($mr, 'SELECT COUNT(*) FROM users WHERE auth_user_id = ?', [$userId]) : 0;
$report['auth users'] = 1;
$report['auth login_attempts'] = $count($authDb, 'SELECT COUNT(*) FROM login_attempts WHERE email_address = ?', [$email]);

echo ($apply ? 'REMOVING' : 'DRY RUN for') . " {$email} ({$userId})\n";
foreach ($report as $what => $n) {
    printf("  %-30s %d\n", $what, $n);
}
if ($mr === null) {
    echo "  (no matchRace database at {$matchRacePath})\n";
}
if (!$apply) {
    echo "Nothing deleted. Re-run with --apply to delete.\n";
    exit(0);
}

$run = static function (PDO $pdo, array $statements, array $args): void {
    $pdo->beginTransaction();
    try {
        foreach ($statements as $sql) {
            $pdo->prepare($sql)->execute($args);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
};

$run($sf, [
    'DELETE FROM boats WHERE owner_user_id = ?',
    'DELETE FROM crews WHERE user_id = ?',
    'DELETE FROM users WHERE auth_user_id = ?',
], [$userId]);
echo "sailFriday.db done\n";

if ($mr !== null) {
    $run($mr, ['DELETE FROM users WHERE auth_user_id = ?'], [$userId]);
    echo "matchRace.db done\n";
}

$run($authDb, ['DELETE FROM users WHERE user_id = ?'], [$userId]);
$run($authDb, ['DELETE FROM login_attempts WHERE email_address = ?'], [$email]);
echo "auth done\n";
