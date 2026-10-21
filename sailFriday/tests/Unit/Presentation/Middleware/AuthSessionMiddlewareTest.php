<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Middleware;

use App\Application\Port\Repository\UserRepositoryInterface;
use App\Domain\Entity\User;
use App\Presentation\Middleware\AuthSessionMiddleware;
use App\Presentation\Response\JsonResponse;
use PHPUnit\Framework\TestCase;
use SailConnect\Auth\Client\SessionVerifier;

class AuthSessionMiddlewareTest extends TestCase
{
    private function identity(): array
    {
        return ['user_id' => 'auth-1', 'email_address' => 'a@example.com', 'first_name' => 'A', 'last_name' => 'B',
            'display_name' => 'AB', 'is_member' => 0, 'membership_number' => null];
    }

    private function statusOf(JsonResponse $r): int
    {
        return (new \ReflectionClass($r))->getProperty('statusCode')->getValue($r);
    }

    private function dataOf(JsonResponse $r): array
    {
        return (new \ReflectionClass($r))->getProperty('data')->getValue($r);
    }

    private function middleware(?array $identity, ?User $localUser): AuthSessionMiddleware
    {
        $verifier = $this->createMock(SessionVerifier::class);
        $verifier->method('currentUser')->willReturn($identity);
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->willReturn($localUser);

        return new AuthSessionMiddleware($verifier, $users);
    }

    public function testNoSessionIsUnauthenticated(): void
    {
        $m = $this->middleware(null, null);

        $this->assertNull($m->authenticate());
        $response = $m->authenticationFailed();
        $this->assertSame(401, $this->statusOf($response));
        $this->assertSame('authentication_required', $this->dataOf($response)['details']['code']);
    }

    public function testSignedInUserWithoutLocalAccountMustEnroll(): void
    {
        $m = $this->middleware($this->identity(), null);

        $this->assertNull($m->authenticate());
        $response = $m->authenticationFailed();
        $this->assertSame(403, $this->statusOf($response));
        $this->assertSame('enrollment_required', $this->dataOf($response)['details']['code']);
    }

    public function testLocalUserYieldsTheLegacyAuthContext(): void
    {
        $user = new User(email: 'a@example.com', accountType: 'crew', isAdmin: true, id: 'auth-1');

        $context = $this->middleware($this->identity(), $user)->authenticate();

        $this->assertSame('auth-1', $context['user_id']);
        $this->assertSame('crew', $context['account_type']);
        $this->assertTrue($context['is_admin']);
        $this->assertSame($this->identity(), $context['identity']);
    }

    public function testIdentityIsAvailableWithoutALocalAccount(): void
    {
        $this->assertSame('auth-1', $this->middleware($this->identity(), null)->identity()['user_id']);
    }
}
