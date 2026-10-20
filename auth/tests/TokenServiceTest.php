<?php

declare(strict_types=1);

namespace SailConnect\Auth\Tests;

use SailConnect\Auth\TokenService;

class TokenServiceTest extends DatabaseTestCase
{
    public function testTokensAreSingleUse(): void
    {
        $user = $this->makeUser();
        $service = new TokenService($this->db);
        $token = $service->issue($user['user_id'], 'password_reset', 3600);

        $this->assertSame($user['user_id'], $service->consume($token, 'password_reset')['user_id']);
        $this->assertNull($service->consume($token, 'password_reset'));
    }

    public function testExpiredTokensAreRejected(): void
    {
        $user = $this->makeUser();
        $service = new TokenService($this->db);
        $token = $service->issue($user['user_id'], 'password_reset', -1);

        $this->assertFalse($service->isValid($token, 'password_reset'));
        $this->assertNull($service->consume($token, 'password_reset'));
    }

    public function testTokenPurposeMustMatch(): void
    {
        $user = $this->makeUser();
        $service = new TokenService($this->db);
        $token = $service->issue($user['user_id'], 'email_change', 3600, 'new@example.com');

        $this->assertNull($service->consume($token, 'password_reset'));
        $this->assertSame('new@example.com', $service->consume($token, 'email_change')['payload']);
    }

    public function testIssuingANewTokenInvalidatesThePreviousOne(): void
    {
        $user = $this->makeUser();
        $service = new TokenService($this->db);
        $first = $service->issue($user['user_id'], 'password_reset', 3600);
        $second = $service->issue($user['user_id'], 'password_reset', 3600);

        $this->assertFalse($service->isValid($first, 'password_reset'));
        $this->assertTrue($service->isValid($second, 'password_reset'));
    }

    public function testOnlyTheHashIsStored(): void
    {
        $user = $this->makeUser();
        $token = (new TokenService($this->db))->issue($user['user_id'], 'password_reset', 3600);

        $this->assertSame(hash('sha256', $token), $this->db->query('SELECT token_hash FROM tokens')->fetchColumn());
    }
}
