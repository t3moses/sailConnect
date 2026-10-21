<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(string $userId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE user_id = ?');
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE email_address = ?');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    public function emailExists(string $email, ?string $exceptUserId = null): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM users WHERE email_address = ? AND user_id <> ?');
        $st->execute([$email, $exceptUserId ?? '']);
        return (bool) $st->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM users ORDER BY last_name, first_name')->fetchAll();
    }

    /** @return list<string> */
    public function activeAdminEmails(): array
    {
        return $this->db->query(
            'SELECT email_address FROM users WHERE is_platform_admin = 1 AND is_platform_active = 1'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function activeAdminCount(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM users WHERE is_platform_admin = 1 AND is_platform_active = 1'
        )->fetchColumn();
    }

    /**
     * @param array<string,mixed> $u first_name,last_name,email_address,password_hash,is_member,
     *                               membership_number, mobile_number?, is_platform_admin?, terms_accepted_at?, terms_url?
     * @return array<string,mixed> the stored row
     */
    public function create(array $u): array
    {
        $now = Database::now();
        $userId = bin2hex(random_bytes(8));

        $tx = $this->begin();
        try {
            $display = $this->uniqueDisplayName($u['first_name'], $u['last_name'], null);
            $st = $this->db->prepare('
                INSERT INTO users (user_id, email_address, password_hash, first_name, last_name, display_name,
                    is_member, membership_number, mobile_number, is_platform_admin, terms_accepted_at, terms_url,
                    created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ');
            $isMember = !empty($u['is_member']) ? 1 : 0;
            $st->execute([
                $userId, $u['email_address'], $u['password_hash'], $u['first_name'], $u['last_name'], $display,
                $isMember, $isMember ? $u['membership_number'] : null,
                ($u['mobile_number'] ?? '') !== '' ? $u['mobile_number'] : null,
                !empty($u['is_platform_admin']) ? 1 : 0,
                $u['terms_accepted_at'] ?? null, $u['terms_url'] ?? null,
                $u['created_at'] ?? $now, $u['created_at'] ?? $now,
            ]);
            $this->end($tx, true);
        } catch (\Throwable $e) {
            $this->end($tx, false);
            throw $e;
        }
        return $this->find($userId);
    }

    /**
     * Update profile fields. Display name is re-derived when the name changes.
     *
     * @param array<string,mixed> $f first_name,last_name,is_member,membership_number,mobile_number
     */
    public function updateProfile(string $userId, array $f): void
    {
        $tx = $this->begin();
        try {
            $display = $this->uniqueDisplayName($f['first_name'], $f['last_name'], $userId);
            $isMember = !empty($f['is_member']) ? 1 : 0;
            $this->db->prepare('
                UPDATE users SET first_name = ?, last_name = ?, display_name = ?, is_member = ?,
                    membership_number = ?, mobile_number = ?, updated_at = ? WHERE user_id = ?
            ')->execute([
                $f['first_name'], $f['last_name'], $display, $isMember,
                $isMember ? $f['membership_number'] : null,
                ($f['mobile_number'] ?? '') !== '' ? $f['mobile_number'] : null, Database::now(), $userId,
            ]);
            $this->end($tx, true);
        } catch (\Throwable $e) {
            $this->end($tx, false);
            throw $e;
        }
    }

    public function setEmail(string $userId, string $email): void
    {
        $this->db->prepare('UPDATE users SET email_address = ?, updated_at = ? WHERE user_id = ?')
            ->execute([$email, Database::now(), $userId]);
    }

    public function setPasswordHash(string $userId, string $hash): void
    {
        $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE user_id = ?')
            ->execute([$hash, Database::now(), $userId]);
    }

    public function setActive(string $userId, bool $active): void
    {
        $this->db->prepare('UPDATE users SET is_platform_active = ?, updated_at = ? WHERE user_id = ?')
            ->execute([$active ? 1 : 0, Database::now(), $userId]);
    }

    public function setAdmin(string $userId, bool $admin): void
    {
        $this->db->prepare('UPDATE users SET is_platform_admin = ?, updated_at = ? WHERE user_id = ?')
            ->execute([$admin ? 1 : 0, Database::now(), $userId]);
    }

    /** Starts a write transaction, or a savepoint when the caller already holds one. */
    private function begin(): string
    {
        if ($this->db->inTransaction()) {
            $this->db->exec('SAVEPOINT user_write');
            return 'savepoint';
        }
        $this->db->exec('BEGIN IMMEDIATE');
        return 'transaction';
    }

    private function end(string $kind, bool $commit): void
    {
        if ($kind === 'savepoint') {
            $this->db->exec($commit ? 'RELEASE user_write' : 'ROLLBACK TO user_write');
        } else {
            $this->db->exec($commit ? 'COMMIT' : 'ROLLBACK');
        }
    }

    /** Must be called inside a write transaction. */
    private function uniqueDisplayName(string $first, string $last, ?string $selfId): string
    {
        $base = Validator::displayNameBase($first, $last);
        $st = $this->db->prepare('SELECT 1 FROM users WHERE display_name = ? AND user_id <> ?');
        $candidate = $base;
        for ($n = 1; ; $n++) {
            $st->execute([$candidate, $selfId ?? '']);
            if (!$st->fetchColumn()) {
                return $candidate;
            }
            $candidate = $base . $n;
        }
    }
}
