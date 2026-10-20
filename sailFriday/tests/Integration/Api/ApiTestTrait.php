<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

/**
 * Shared utilities for API tests
 */
trait ApiTestTrait
{
    private function makeRequest(string $method, string $url, ?array $body = null, ?array $headers = []): array
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        if ($body !== null) {
            $jsonBody = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            $headers[] = 'Content-Type: application/json';
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            throw new \RuntimeException("Curl request failed: {$error}");
        }

        // Note: curl_close() is deprecated in PHP 8.5+ as handles auto-close since PHP 8.0

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true),
        ];
    }

    private function makeUniqueEmail(string $prefix): string
    {
        $suffix = str_replace('.', '', (string)microtime(true));
        return sprintf('%s.%s@example.com', $prefix, $suffix);
    }

    private function makeUniqueSuffix(): string
    {
        return str_replace('.', '', (string)microtime(true));
    }

    /**
     * Create a sailConnect identity plus a live session directly in the auth database the dev server uses
     * (auth/.env). Returns the raw session token to send as the sc_session cookie.
     *
     * @return array{token:string, authUserId:string, email:string, firstName:string, lastName:string}
     */
    private function createAuthSession(string $emailPrefix, string $firstName, string $lastName, ?string $membershipNumber = null): array
    {
        $authDir = dirname(__DIR__, 3) . '/../auth';
        $config = \SailConnect\Auth\Config::load($authDir);
        $db = \SailConnect\Auth\Database::connect($config->dbPath());

        $email = $this->makeUniqueEmail($emailPrefix);
        $user = (new \SailConnect\Auth\UserRepository($db))->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email_address' => $email,
            'password_hash' => password_hash('TestPass123', PASSWORD_BCRYPT),
            'is_member' => $membershipNumber !== null,
            'membership_number' => $membershipNumber,
        ]);
        $sessions = new \SailConnect\Auth\SessionService($db, $config->inactivitySeconds(), $config->sessionMaxSeconds());

        return [
            'token' => $sessions->create($user['user_id'], '127.0.0.1', 'phpunit'),
            'authUserId' => $user['user_id'],
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];
    }

    /** Cookie header that authenticates a request as the given session token. */
    private function sessionCookie(string $token): string
    {
        return 'Cookie: sc_session=' . $token;
    }

    private function createTestCrew(string $baseUrl): array
    {
        $suffix = $this->makeUniqueSuffix();
        $firstName = "TestCrew{$suffix}";
        $lastName = "Member";
        $session = $this->createAuthSession('test.crew', $firstName, $lastName);

        $response = $this->makeRequest('POST', "{$baseUrl}/auth/register", [
            'accountType' => 'crew',
            'profile' => [
                'displayName' => "Test Crew {$suffix}",
                'skill' => 1,
            ],
        ], [$this->sessionCookie($session['token'])]);

        $this->assertEquals(201, $response['status']);

        return [
            'token' => $session['token'],
            'authUserId' => $session['authUserId'],
            'userId' => $response['body']['data']['user']['id'] ?? null,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];
    }

    private function createTestBoatOwner(string $baseUrl): array
    {
        $suffix = $this->makeUniqueSuffix();
        $ownerFirstName = "TestBoat{$suffix}";
        $ownerLastName = "Owner";
        $session = $this->createAuthSession('test.boat', $ownerFirstName, $ownerLastName);

        $response = $this->makeRequest('POST', "{$baseUrl}/auth/register", [
            'accountType' => 'boat_owner',
            'profile' => [
                'displayName' => "Test Boat {$suffix}",
                'ownerMobile' => '555-1234',
                'minBerths' => 2,
                'maxBerths' => 4,
                'assistanceRequired' => false,
                'socialPreference' => true,
            ],
        ], [$this->sessionCookie($session['token'])]);

        $this->assertEquals(201, $response['status']);

        return [
            'token' => $session['token'],
            'authUserId' => $session['authUserId'],
            'userId' => $response['body']['data']['user']['id'] ?? null,
            'ownerFirstName' => $ownerFirstName,
            'ownerLastName' => $ownerLastName,
        ];
    }

    private function createTestAdmin(string $baseUrl): array
    {
        $suffix = $this->makeUniqueSuffix();
        $firstName = "AdminTest{$suffix}";
        $lastName = "User";
        $session = $this->createAuthSession('test.admin', $firstName, $lastName);

        // Create the Sail Friday user directly (the register endpoint never grants isAdmin)
        $pdo = \App\Infrastructure\Persistence\SQLite\Connection::getInstance();
        $stmt = $pdo->prepare('
            INSERT INTO users (email, password_hash, account_type, is_admin, auth_user_id, created_at, updated_at)
            VALUES (?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([$session['email'], '', 'crew', $session['authUserId']]);
        $userId = (int)$pdo->lastInsertId();

        // Create a crew profile for the admin (required for some operations)
        $crewKey = strtolower(str_replace(' ', '', $firstName) . str_replace(' ', '', $lastName));

        $stmt = $pdo->prepare('
            INSERT INTO crews (
                key, display_name, first_name, last_name, skill, user_id,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([$crewKey, "Admin Test {$suffix}", $firstName, $lastName, $userId]);

        return [
            'token' => $session['token'],
            'authUserId' => $session['authUserId'],
            'userId' => $userId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $session['email'],
        ];
    }

    private function cleanupTestUser(?int $userId): void
    {
        if ($userId) {
            \Tests\Integration\UserTestHelper::deleteTestUser($userId);
        }
    }
}
