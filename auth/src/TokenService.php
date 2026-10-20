<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use PDO;

/** Single-use, expiring tokens (password reset, email change). Only hashes are stored. */
final class TokenService
{
    public function __construct(private PDO $db)
    {
    }

    public function issue(string $userId, string $purpose, int $ttlSeconds, ?string $payload = null): string
    {
        $this->db->prepare('DELETE FROM tokens WHERE user_id = ? AND purpose = ?')->execute([$userId, $purpose]);
        $this->db->prepare('DELETE FROM tokens WHERE expires_at < ?')->execute([Database::now()]);

        $token = bin2hex(random_bytes(32));
        $this->db->prepare('
            INSERT INTO tokens (token_hash, user_id, purpose, payload, expires_at, created_at) VALUES (?,?,?,?,?,?)
        ')->execute([hash('sha256', $token), $userId, $purpose, $payload, gmdate('Y-m-d H:i:s', time() + $ttlSeconds), Database::now()]);
        return $token;
    }

    /**
     * Atomically consumes a token. @return array{user_id:string,payload:?string}|null
     */
    public function consume(string $token, string $purpose): ?array
    {
        $hash = hash('sha256', $token);
        $st = $this->db->prepare('SELECT user_id, payload, expires_at FROM tokens WHERE token_hash = ? AND purpose = ?');
        $st->execute([$hash, $purpose]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $del = $this->db->prepare('DELETE FROM tokens WHERE token_hash = ?');
        $del->execute([$hash]);
        if ($del->rowCount() !== 1 || $row['expires_at'] < Database::now()) {
            return null;
        }
        return ['user_id' => $row['user_id'], 'payload' => $row['payload']];
    }

    /** Non-consuming check, so a reset form can be refused early. */
    public function isValid(string $token, string $purpose): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM tokens WHERE token_hash = ? AND purpose = ? AND expires_at >= ?');
        $st->execute([hash('sha256', $token), $purpose, Database::now()]);
        return (bool) $st->fetchColumn();
    }
}
