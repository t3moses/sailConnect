<?php

declare(strict_types=1);

namespace Tests\Integration\Application\UseCase\User;

use App\Application\DTO\Request\UpdateProfileRequest;
use App\Application\Exception\ValidationException;
use App\Application\Port\Service\EmailServiceInterface;
use App\Application\Port\Service\EmailTemplateServiceInterface;
use App\Application\UseCase\User\GetUserProfileUseCase;
use App\Application\UseCase\User\UpdateUserProfileUseCase;
use App\Domain\Enum\SkillLevel;
use App\Infrastructure\Persistence\SQLite\BoatRepository;
use App\Infrastructure\Persistence\SQLite\CrewRepository;
use App\Infrastructure\Persistence\SQLite\UserRepository;
use App\Infrastructure\Persistence\SQLite\Connection;
use Tests\Integration\IntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * Integration tests for UpdateUserProfileUseCase
 *
 * Tests the complete profile update workflow including:
 * - Rejection of email/password changes (owned by sailConnect)
 * - Crew profile updates (display name, mobile, skill, etc.)
 * - Boat profile updates (display name, mobile, berths, etc.)
 * - Validation scenarios
 * - Edge cases and error conditions
 */
class UpdateUserProfileUseCaseTest extends IntegrationTestCase
{
    private UpdateUserProfileUseCase $useCase;
    private GetUserProfileUseCase $getUserProfileUseCase;
    private UserRepository $userRepository;
    private CrewRepository $crewRepository;
    private BoatRepository $boatRepository;

    protected function setUp(): void
    {
        parent::setUp();  // Runs migrations, initializes season config, sets test connection

        // Initialize repositories
        $this->userRepository = new UserRepository();
        $this->crewRepository = new CrewRepository();
        $this->boatRepository = new BoatRepository();

        // Initialize use cases
        $this->getUserProfileUseCase = new GetUserProfileUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository
        );

        $this->useCase = new UpdateUserProfileUseCase(
            $this->userRepository,
            $this->crewRepository,
            $this->boatRepository,
            $this->getUserProfileUseCase,
            $this->createMock(EmailServiceInterface::class),
            $this->createMock(EmailTemplateServiceInterface::class),
            'admin@example.com',
            new NullLogger()
        );
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create test user with optional crew/boat profile (extends base class method)
     */
    protected function createTestUser(
        string $email = 'test@example.com',
        string $accountType = 'crew',
        bool $isAdmin = false,  // Match base class signature
        bool $createCrewProfile = false,
        bool $createBoatProfile = false
    ): int {
        // Use base class to create user
        $userId = parent::createTestUser($email, $accountType, $isAdmin);

        // Create crew profile if requested
        if ($createCrewProfile) {
            $this->createCrewProfileForUser($userId);
        }

        // Create boat profile if requested
        if ($createBoatProfile) {
            $this->createBoatProfileForUser($userId);
        }

        return $userId;
    }

    /**
     * Create crew profile for user
     */
    protected function createCrewProfileForUser(int $userId, array $overrides = []): int
    {
        $key = $overrides['key'] ?? 'crew_' . $userId;

        $stmt = $this->pdo->prepare('
            INSERT INTO crews (
                key, display_name, first_name, last_name, skill, mobile,
                membership_number, social_preference, experience,
                user_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        $stmt->execute([
            $key,
            $overrides['displayName'] ?? 'TestCrew',
            $overrides['firstName'] ?? 'Test',
            $overrides['lastName'] ?? 'Crew',
            $overrides['skill'] ?? SkillLevel::INTERMEDIATE->value,
            $overrides['mobile'] ?? '555-1234',
            $overrides['membershipNumber'] ?? '12345',
            $overrides['socialPreference'] ?? 'No', // Must be 'Yes' or 'No'
            $overrides['experience'] ?? 'Some sailing experience',
            $userId
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Create boat profile for user
     */
    protected function createBoatProfileForUser(int $userId, array $overrides = []): int
    {
        $key = $overrides['key'] ?? 'boat_' . $userId;

        $stmt = $this->pdo->prepare('
            INSERT INTO boats (
                key, display_name, owner_first_name, owner_last_name, owner_mobile,
                min_berths, max_berths, assistance_required, social_preference,
                owner_user_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        $stmt->execute([
            $key,
            $overrides['displayName'] ?? 'TestBoat',
            $overrides['ownerFirstName'] ?? 'Test',
            $overrides['ownerLastName'] ?? 'Owner',
            $overrides['ownerMobile'] ?? '555-5678',
            $overrides['minBerths'] ?? 2,
            $overrides['maxBerths'] ?? 4,
            $overrides['assistanceRequired'] ?? 'No', // Must be 'Yes' or 'No'
            $overrides['socialPreference'] ?? 'No', // Must be 'Yes' or 'No'
            $userId
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get user email from database
     */
    protected function getUserEmail(int $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get crew display name from database
     */
    protected function getCrewDisplayName(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT display_name FROM crews WHERE user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Get boat display name from database
     */
    protected function getBoatDisplayName(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT display_name FROM boats WHERE owner_user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Get crew membership_rank value from database
     */
    protected function getCrewRankMembership(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT membership_rank FROM crews WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    // ==================== HAPPY PATH TESTS ====================

    public function testEmailChangeIsRejectedBecauseSailConnectOwnsIt(): void
    {
        $userId = $this->createTestUser('original@example.com');
        $request = new UpdateProfileRequest(email: 'updated@example.com');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email address is managed in your sailConnect account');

        $this->useCase->execute($userId, $request);
    }

    public function testPasswordChangeIsRejectedBecauseSailConnectOwnsIt(): void
    {
        $userId = $this->createTestUser();
        $request = new UpdateProfileRequest(password: 'NewSecurePass123!');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password is managed in your sailConnect account');

        $this->useCase->execute($userId, $request);
    }

    public function testIdentityOwnedCrewFieldsAreIgnored(): void
    {
        $userId = $this->createTestUser('crew@example.com', 'crew', createCrewProfile: true);
        $before = $this->crewRepository->findByUserId($userId);
        $request = new UpdateProfileRequest(
            crewProfile: [
                'firstName' => 'Changed',
                'lastName' => 'Name',
                'membershipNumber' => '99999',
                'mobile' => '555-1212',
            ]
        );

        $response = $this->useCase->execute($userId, $request);

        $this->assertSame($before->getFirstName(), $response->crewProfile->firstName);
        $this->assertSame($before->getLastName(), $response->crewProfile->lastName);
        $this->assertSame($before->getMembershipNumber(), $response->crewProfile->membershipNumber);
        $this->assertSame('555-1212', $response->crewProfile->mobile);
    }

    public function testUpdateCrewProfileOnly(): void
    {
        // Arrange
        $userId = $this->createTestUser('crew@example.com', 'crew', createCrewProfile: true);
        $request = new UpdateProfileRequest(
            crewProfile: [
                'displayName' => 'Updated Crew Name',
                'mobile' => '555-9999',
                'skill' => SkillLevel::ADVANCED->value,
                'socialPreference' => false,
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertNotNull($response->crewProfile);
        $this->assertEquals('Updated Crew Name', $response->crewProfile->displayName);
        $this->assertEquals('555-9999', $response->crewProfile->mobile);
        $this->assertEquals(SkillLevel::ADVANCED->value, $response->crewProfile->skill);
    }

    public function testUpdateBoatProfileOnly(): void
    {
        // Arrange
        $userId = $this->createTestUser('boat@example.com', 'boat_owner', createBoatProfile: true);
        $request = new UpdateProfileRequest(
            boatProfile: [
                'displayName' => 'Updated Boat Name',
                'ownerMobile' => '555-8888',
                'minBerths' => 3,
                'maxBerths' => 6,
                'assistanceRequired' => true,
                'socialPreference' => false
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertNotNull($response->boatProfile);
        $this->assertEquals('Updated Boat Name', $response->boatProfile->displayName);
        $this->assertEquals('555-8888', $response->boatProfile->ownerMobile);
        $this->assertEquals(3, $response->boatProfile->minBerths);
        $this->assertEquals(6, $response->boatProfile->maxBerths);
        $this->assertTrue($response->boatProfile->assistanceRequired);
    }

    public function testUpdateCrewProfileWithDisplayNameAndMobile(): void
    {
        // Arrange
        $userId = $this->createTestUser('user@example.com', 'crew', createCrewProfile: true);
        $request = new UpdateProfileRequest(
            crewProfile: [
                'displayName' => 'New Display Name',
                'mobile' => '555-7777'
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals('New Display Name', $response->crewProfile->displayName);
        $this->assertEquals('555-7777', $response->crewProfile->mobile);
    }

    public function testUpdateBoatProfileWithDisplayNameAndBerths(): void
    {
        // Arrange
        $userId = $this->createTestUser('boat@example.com', 'boat_owner', createBoatProfile: true);
        $request = new UpdateProfileRequest(
            boatProfile: [
                'displayName' => 'New Boat Name',
                'minBerths' => 4
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals('New Boat Name', $response->boatProfile->displayName);
        $this->assertEquals(4, $response->boatProfile->minBerths);
    }

    public function testUpdateAllFieldsSimultaneously(): void
    {
        // Arrange: Create user with both profiles (flex user)
        $userId = $this->createTestUser('flex@example.com', 'crew', createCrewProfile: true);
        // Also add boat profile
        $this->createBoatProfileForUser($userId);

        $request = new UpdateProfileRequest(
            crewProfile: [
                'displayName' => 'Flex Crew',
                'skill' => SkillLevel::ADVANCED->value
            ],
            boatProfile: [
                'displayName' => 'Flex Boat',
                'maxBerths' => 5
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals('Flex Crew', $response->crewProfile->displayName);
        $this->assertEquals('Flex Boat', $response->boatProfile->displayName);
    }

    // ==================== VALIDATION TESTS ====================

    public function testNoUpdatesProvidedThrowsValidationException(): void
    {
        // Arrange
        $userId = $this->createTestUser();
        $request = new UpdateProfileRequest(); // No fields provided

        // Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No updates provided');

        // Act
        $this->useCase->execute($userId, $request);
    }

    public function testUpdateCrewProfileWhenNoneExistsThrowsValidationException(): void
    {
        // Arrange
        $userId = $this->createTestUser('nocrew@example.com', 'crew', createCrewProfile: false);
        $request = new UpdateProfileRequest(
            crewProfile: [
                'displayName' => 'Should Fail'
            ]
        );

        // Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User does not have a crew profile');

        // Act
        $this->useCase->execute($userId, $request);
    }

    public function testUpdateBoatProfileWhenNoneExistsThrowsValidationException(): void
    {
        // Arrange
        $userId = $this->createTestUser('noboat@example.com', 'boat_owner', createBoatProfile: false);
        $request = new UpdateProfileRequest(
            boatProfile: [
                'displayName' => 'Should Fail'
            ]
        );

        // Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User does not have a boat profile');

        // Act
        $this->useCase->execute($userId, $request);
    }

    // ==================== EDGE CASE TESTS ====================

    public function testUserNotFoundThrowsRuntimeException(): void
    {
        // Arrange
        $nonExistentUserId = 99999;
        $request = new UpdateProfileRequest(
            crewProfile: ['mobile' => '555-0000']
        );

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        // Act
        $this->useCase->execute($nonExistentUserId, $request);
    }

    public function testUpdateCrewProfileWithNullOptionalFields(): void
    {
        // Arrange
        $userId = $this->createTestUser('crew@example.com', 'crew', createCrewProfile: true);
        $request = new UpdateProfileRequest(
            crewProfile: [
                'displayName' => 'Updated Name',
                'mobile' => null, // Null optional field
                'experience' => null
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals('Updated Name', $response->crewProfile->displayName);
        $this->assertNotNull($response->crewProfile);
    }

    public function testUpdateCrewProfileWithDifferentSkillLevels(): void
    {
        // Arrange
        $userId = $this->createTestUser('crew@example.com', 'crew', createCrewProfile: true);

        // Test NOVICE
        $request1 = new UpdateProfileRequest(
            crewProfile: ['skill' => SkillLevel::NOVICE->value]
        );
        $response1 = $this->useCase->execute($userId, $request1);
        $this->assertEquals(SkillLevel::NOVICE->value, $response1->crewProfile->skill);

        // Test INTERMEDIATE
        $request2 = new UpdateProfileRequest(
            crewProfile: ['skill' => SkillLevel::INTERMEDIATE->value]
        );
        $response2 = $this->useCase->execute($userId, $request2);
        $this->assertEquals(SkillLevel::INTERMEDIATE->value, $response2->crewProfile->skill);

        // Test ADVANCED
        $request3 = new UpdateProfileRequest(
            crewProfile: ['skill' => SkillLevel::ADVANCED->value]
        );
        $response3 = $this->useCase->execute($userId, $request3);
        $this->assertEquals(SkillLevel::ADVANCED->value, $response3->crewProfile->skill);
    }

    public function testUpdateBoatProfileWithBerthsValidation(): void
    {
        // Arrange
        $userId = $this->createTestUser('boat@example.com', 'boat_owner', createBoatProfile: true);
        $request = new UpdateProfileRequest(
            boatProfile: [
                'minBerths' => 2,
                'maxBerths' => 8
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals(2, $response->boatProfile->minBerths);
        $this->assertEquals(8, $response->boatProfile->maxBerths);
    }

    public function testUpdateBoatProfileWithAssistanceRequiredToggle(): void
    {
        // Arrange
        $userId = $this->createTestUser('boat@example.com', 'boat_owner', createBoatProfile: true);

        // Toggle to true
        $request1 = new UpdateProfileRequest(
            boatProfile: ['assistanceRequired' => true]
        );
        $response1 = $this->useCase->execute($userId, $request1);
        $this->assertTrue($response1->boatProfile->assistanceRequired);

        // Toggle to false
        $request2 = new UpdateProfileRequest(
            boatProfile: ['assistanceRequired' => false]
        );
        $response2 = $this->useCase->execute($userId, $request2);
        $this->assertFalse($response2->boatProfile->assistanceRequired);
    }

    public function testUpdateCrewSocialPreference(): void
    {
        // Arrange
        $userId = $this->createTestUser('crew@example.com', 'crew', createCrewProfile: true);

        $request = new UpdateProfileRequest(
            crewProfile: ['socialPreference' => true]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertTrue($response->crewProfile->socialPreference);
    }

    public function testPartialCrewProfileUpdate(): void
    {
        // Arrange
        $userId = $this->createTestUser('crew@example.com', 'crew', createCrewProfile: true);
        $originalDisplayName = $this->getCrewDisplayName($userId);

        $request = new UpdateProfileRequest(
            crewProfile: [
                'mobile' => '555-1111' // Only update mobile
                // displayName not provided, should remain unchanged
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals('555-1111', $response->crewProfile->mobile);
        $this->assertEquals($originalDisplayName, $response->crewProfile->displayName);
    }

    public function testPartialBoatProfileUpdate(): void
    {
        // Arrange
        $userId = $this->createTestUser('boat@example.com', 'boat_owner', createBoatProfile: true);
        $originalDisplayName = $this->getBoatDisplayName($userId);

        $request = new UpdateProfileRequest(
            boatProfile: [
                'ownerMobile' => '555-2222' // Only update mobile
                // displayName not provided, should remain unchanged
            ]
        );

        // Act
        $response = $this->useCase->execute($userId, $request);

        // Assert
        $this->assertEquals('555-2222', $response->boatProfile->ownerMobile);
        $this->assertEquals($originalDisplayName, $response->boatProfile->displayName);
    }

}
