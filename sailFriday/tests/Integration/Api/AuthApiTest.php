<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use PHPUnit\Framework\TestCase;

/**
 * API tests for the Sail Friday side of authentication.
 *
 * Sign-in, sign-out and passwords live in the sailConnect auth service; here a session is created
 * directly in the auth database and sent as the shared sc_session cookie.
 */
class AuthApiTest extends TestCase
{
    use ApiTestTrait;

    private string $baseUrl = 'http://localhost:8000/api';

    public function testRegisterCrewAccountEnrollsSignedInUser(): void
    {
        $suffix = $this->makeUniqueSuffix();
        $session = $this->createAuthSession('john.doe.crew', "John{$suffix}", "Doe{$suffix}");

        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'crew',
            'profile' => ['displayName' => "John Doe {$suffix}", 'skill' => 1],
        ], [$this->sessionCookie($session['token'])]);

        $this->assertEquals(201, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertArrayNotHasKey('token', $response['body']['data']);

        $user = $response['body']['data']['user'];
        $this->assertEquals($session['email'], $user['email']);
        $this->assertEquals('crew', $user['accountType']);
        $this->assertFalse($user['isAdmin']);

        // Names come from the sailConnect identity
        $profile = $this->makeRequest('GET', "{$this->baseUrl}/users/me", null, [$this->sessionCookie($session['token'])]);
        $this->assertEquals("John{$suffix}", $profile['body']['data']['crewProfile']['firstName']);

        $this->cleanupTestUser($user['id']);
    }

    public function testRegisterIgnoresNamesSubmittedInProfile(): void
    {
        $suffix = $this->makeUniqueSuffix();
        $session = $this->createAuthSession('real.name', "Real{$suffix}", 'Person');

        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'crew',
            'profile' => ['firstName' => 'Spoofed', 'lastName' => 'Name', 'skill' => 1],
        ], [$this->sessionCookie($session['token'])]);

        $this->assertEquals(201, $response['status']);

        $profile = $this->makeRequest('GET', "{$this->baseUrl}/users/me", null, [$this->sessionCookie($session['token'])]);
        $this->assertEquals("Real{$suffix}", $profile['body']['data']['crewProfile']['firstName']);
        $this->assertEquals('Person', $profile['body']['data']['crewProfile']['lastName']);

        $this->cleanupTestUser($response['body']['data']['user']['id']);
    }

    public function testRegisterBoatOwnerAccount(): void
    {
        $suffix = $this->makeUniqueSuffix();
        $session = $this->createAuthSession('mary.boat', "Mary{$suffix}", 'Captain');

        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'boat_owner',
            'profile' => [
                'displayName' => "Sea Breeze {$suffix}",
                'minBerths' => 2,
                'maxBerths' => 4,
            ],
        ], [$this->sessionCookie($session['token'])]);

        $this->assertEquals(201, $response['status']);
        $this->assertEquals('boat_owner', $response['body']['data']['user']['accountType']);

        $this->cleanupTestUser($response['body']['data']['user']['id']);
    }

    public function testRegisterWithoutSessionIsUnauthorized(): void
    {
        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'crew',
            'profile' => ['skill' => 1],
        ]);

        $this->assertEquals(401, $response['status']);
        $this->assertEquals('authentication_required', $response['body']['details']['code']);
    }

    public function testRegisterValidationErrors(): void
    {
        $session = $this->createAuthSession('validation', 'Valid' . $this->makeUniqueSuffix(), 'Case');
        $cookie = [$this->sessionCookie($session['token'])];

        // Invalid account type
        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'invalid_type',
            'profile' => ['skill' => 1],
        ], $cookie);
        $this->assertEquals(400, $response['status']);

        // Boat with max berths below min berths
        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'boat_owner',
            'profile' => ['minBerths' => 4, 'maxBerths' => 2],
        ], $cookie);
        $this->assertEquals(400, $response['status']);
    }

    public function testRegisterTwiceConflicts(): void
    {
        $testData = $this->createTestCrew($this->baseUrl);

        $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/register", [
            'accountType' => 'crew',
            'profile' => ['skill' => 1],
        ], [$this->sessionCookie($testData['token'])]);

        $this->assertEquals(409, $response['status']);

        $this->cleanupTestUser($testData['userId']);
    }

    public function testSignedInUserWithoutSailFridayAccountMustEnroll(): void
    {
        $session = $this->createAuthSession('unenrolled', 'Un' . $this->makeUniqueSuffix(), 'Enrolled');
        $cookie = [$this->sessionCookie($session['token'])];

        $response = $this->makeRequest('GET', "{$this->baseUrl}/users/me", null, $cookie);

        $this->assertEquals(403, $response['status']);
        $this->assertEquals('enrollment_required', $response['body']['details']['code']);
    }

    public function testGetIdentityWorksBeforeEnrollment(): void
    {
        $suffix = $this->makeUniqueSuffix();
        $session = $this->createAuthSession('identity', "Ida{$suffix}", 'Lee', '7001-1234');

        $response = $this->makeRequest('GET', "{$this->baseUrl}/auth/identity", null, [$this->sessionCookie($session['token'])]);

        $this->assertEquals(200, $response['status']);
        $identity = $response['body']['data']['identity'];
        $this->assertEquals($session['email'], $identity['email']);
        $this->assertEquals("Ida{$suffix}", $identity['firstName']);
        $this->assertTrue($identity['isMember']);
        $this->assertEquals('7001-1234', $identity['membershipNumber']);
    }

    public function testGetSession(): void
    {
        $testData = $this->createTestCrew($this->baseUrl);

        $response = $this->makeRequest('GET', "{$this->baseUrl}/auth/session", null, [
            "Cookie: sc_session={$testData['token']}",
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);

        $user = $response['body']['data']['user'];
        $this->assertArrayHasKey('email', $user);
        $this->assertEquals($testData['userId'], $user['id']);

        $this->cleanupTestUser($testData['userId']);
    }

    public function testSessionEndedBySignOffIsRejected(): void
    {
        $testData = $this->createTestCrew($this->baseUrl);
        $cookie = [$this->sessionCookie($testData['token'])];

        $this->assertEquals(200, $this->makeRequest('GET', "{$this->baseUrl}/auth/session", null, $cookie)['status']);

        // Sign-off at the auth service deletes the session row
        $authDb = \SailConnect\Auth\Database::connect(\SailConnect\Auth\Config::load(dirname(__DIR__, 3) . '/../auth')->dbPath());
        (new \SailConnect\Auth\SessionService($authDb, 60, 3600))->destroy($testData['token']);

        $this->assertEquals(401, $this->makeRequest('GET', "{$this->baseUrl}/auth/session", null, $cookie)['status']);

        $this->cleanupTestUser($testData['userId']);
    }

    public function testRemovedCredentialEndpointsAreGone(): void
    {
        foreach (['login', 'logout', 'forgot-password', 'reset-password'] as $endpoint) {
            $response = $this->makeRequest('POST', "{$this->baseUrl}/auth/{$endpoint}", []);
            $this->assertEquals(404, $response['status'], "/auth/{$endpoint} should no longer exist");
        }
    }

    public function testAuthenticationFailureWithoutSession(): void
    {
        $response = $this->makeRequest('GET', "{$this->baseUrl}/assignments");

        $this->assertEquals(401, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
    }
}
