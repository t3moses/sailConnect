<?php

declare(strict_types=1);

namespace SailConnect\Auth\Tests;

use SailConnect\Auth\SessionService;

class SessionServiceTest extends DatabaseTestCase
{
    private const INACTIVITY = 600;
    private const MAX_AGE = 86400;

    private function service(): SessionService
    {
        return new SessionService($this->db, self::INACTIVITY, self::MAX_AGE);
    }

    private function age(string $token, string $column, int $seconds): void
    {
        $this->db->prepare("UPDATE sessions SET $column = ? WHERE session_hash = ?")
            ->execute([gmdate('Y-m-d H:i:s', time() - $seconds), hash('sha256', $token)]);
    }

    public function testValidSessionReturnsTheUserWithoutThePasswordHash(): void
    {
        $user = $this->makeUser();
        $token = $this->service()->create($user['user_id'], '127.0.0.1', 'test');

        $found = $this->service()->validate($token);

        $this->assertSame($user['user_id'], $found['user_id']);
        $this->assertArrayNotHasKey('password_hash', $found);
    }

    public function testOnlyTheHashOfTheTokenIsStored(): void
    {
        $user = $this->makeUser();
        $token = $this->service()->create($user['user_id'], null, null);

        $stored = $this->db->query('SELECT session_hash FROM sessions')->fetchColumn();

        $this->assertNotSame($token, $stored);
        $this->assertSame(hash('sha256', $token), $stored);
    }

    public function testSessionExpiresAfterInactivity(): void
    {
        $user = $this->makeUser();
        $token = $this->service()->create($user['user_id'], null, null);
        $this->age($token, 'last_touch', self::INACTIVITY + 5);

        $this->assertNull($this->service()->validate($token));
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
    }

    public function testActivityExtendsTheSession(): void
    {
        $user = $this->makeUser();
        $token = $this->service()->create($user['user_id'], null, null);
        $this->age($token, 'last_touch', self::INACTIVITY - 60);

        $this->assertNotNull($this->service()->validate($token));
        $this->assertNotNull($this->service()->validate($token), 'last_touch must have been slid forward');

        $touch = $this->db->query('SELECT last_touch FROM sessions')->fetchColumn();
        $this->assertGreaterThan(time() - 10, strtotime($touch . ' UTC'));
    }

    public function testSessionHasAnAbsoluteMaximumAge(): void
    {
        $user = $this->makeUser();
        $token = $this->service()->create($user['user_id'], null, null);
        $this->age($token, 'created_at', self::MAX_AGE + 5);

        $this->assertNull($this->service()->validate($token));
    }

    public function testDeactivatedAccountsAreSignedOut(): void
    {
        $user = $this->makeUser();
        $token = $this->service()->create($user['user_id'], null, null);
        $this->users->setActive($user['user_id'], false);

        $this->assertNull($this->service()->validate($token));
    }

    public function testDestroyAllForUserKeepsTheNamedSession(): void
    {
        $user = $this->makeUser();
        $keep = $this->service()->create($user['user_id'], null, null);
        $drop = $this->service()->create($user['user_id'], null, null);

        $this->service()->destroyAllForUser($user['user_id'], $keep);

        $this->assertNotNull($this->service()->validate($keep));
        $this->assertNull($this->service()->validate($drop));
    }

    public function testMalformedTokensAreRejected(): void
    {
        $this->assertNull($this->service()->validate(''));
        $this->assertNull($this->service()->validate("' OR 1=1 --"));
    }
}
