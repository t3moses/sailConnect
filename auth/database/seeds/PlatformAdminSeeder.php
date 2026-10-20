<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;
use SailConnect\Auth\Database;
use SailConnect\Auth\UserRepository;
use SailConnect\Auth\Validator;

/**
 * Creates the first platform admin from SEED_ADMIN_* in .env. Safe to re-run: it does nothing
 * when that email address already exists.
 */
class PlatformAdminSeeder extends AbstractSeed
{
    public function run(): void
    {
        $email = strtolower(trim((string) ($_ENV['SEED_ADMIN_EMAIL'] ?? '')));
        $first = trim((string) ($_ENV['SEED_ADMIN_FIRST_NAME'] ?? ''));
        $last = trim((string) ($_ENV['SEED_ADMIN_LAST_NAME'] ?? ''));
        $password = (string) ($_ENV['SEED_ADMIN_PASSWORD'] ?? '');

        if (!Validator::email($email) || !Validator::name($first) || !Validator::name($last)) {
            throw new RuntimeException('Set SEED_ADMIN_EMAIL, SEED_ADMIN_FIRST_NAME and SEED_ADMIN_LAST_NAME in .env.');
        }
        if ($errors = Validator::passwordErrors($password)) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD: ' . implode(' ', $errors));
        }

        /** @var PDO $pdo */
        $pdo = $this->getAdapter()->getConnection();
        $users = new UserRepository($pdo);
        if ($users->findByEmail($email)) {
            echo "Admin $email already exists; skipping.\n";
            return;
        }
        $users->create([
            'first_name' => $first, 'last_name' => $last, 'email_address' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'is_member' => false, 'is_platform_admin' => true,
            'terms_accepted_at' => Database::now(),
        ]);
        echo "Created platform admin $email.\n";
    }
}
