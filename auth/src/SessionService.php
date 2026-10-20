<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use PDO;

/**
 * Server-side sessions with sliding inactivity expiry and an absolute age cap.
 * The cookie holds a random token; only its SHA-256 hash is stored.
 */
final class SessionService
{
    /** last_touch is only rewritten when older than this, to limit write load. */
    private const TOUCH_GRANULARITY = 30;

    public function __construct(
        private PDO $db,
        private int $inactivitySeconds,
        private int $maxSeconds,
    ) {
    }

    /** @return string the raw token to put in the cookie */
    public function create(string $userId, ?string $ip, ?string $userAgent): string
    {
        $token = bin2hex(random_bytes(32));
        $now = Database::now();
        $this->db->prepare('
            INSERT INTO sessions (session_hash, user_id, created_at, last_touch, ip_address, user_agent)
            VALUES (?,?,?,?,?,?)
        ')->execute([hash('sha256', $token), $userId, $now, $now, $ip, $userAgent !== null ? substr($userAgent, 0, 255) : null]);
        $this->purgeExpired();
        return $token;
    }

    /**
     * Returns the user row for a live session and slides its inactivity window,
     * or null (deleting the session) when it is unknown, expired, or the account is inactive.
     *
     * @return array<string,mixed>|null
     */
    public function validate(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $st = $this->db->prepare('
            SELECT s.created_at AS s_created, s.last_touch, u.*
            FROM sessions s JOIN users u ON u.user_id = s.user_id
            WHERE s.session_hash = ?
        ');
        $st->execute([$hash]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }

        $now = time();
        $idle = $now - Database::ts($row['last_touch']);
        $age = $now - Database::ts($row['s_created']);
        if ($idle > $this->inactivitySeconds || $age > $this->maxSeconds || !(int) $row['is_platform_active']) {
            $this->destroy($token);
            return null;
        }
        if ($idle >= self::TOUCH_GRANULARITY) {
            $this->db->prepare('UPDATE sessions SET last_touch = ? WHERE session_hash = ?')
                ->execute([Database::now(), $hash]);
        }
        unset($row['s_created'], $row['last_touch'], $row['password_hash']);
        return $row;
    }

    public function destroy(string $token): void
    {
        $this->db->prepare('DELETE FROM sessions WHERE session_hash = ?')->execute([hash('sha256', $token)]);
    }

    public function destroyAllForUser(string $userId, ?string $exceptToken = null): void
    {
        $this->db->prepare('DELETE FROM sessions WHERE user_id = ? AND session_hash <> ?')
            ->execute([$userId, $exceptToken !== null ? hash('sha256', $exceptToken) : '']);
    }

    private function purgeExpired(): void
    {
        $this->db->prepare('DELETE FROM sessions WHERE last_touch < ? OR created_at < ?')->execute([
            gmdate('Y-m-d H:i:s', time() - $this->inactivitySeconds),
            gmdate('Y-m-d H:i:s', time() - $this->maxSeconds),
        ]);
    }
}
