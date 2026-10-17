<?php

declare(strict_types=1);

namespace Tests\Integration\Application\UseCase\Auth;

use App\Application\UseCase\Auth\GetSessionUseCase;
use App\Application\Exception\UserNotFoundException;
use App\Infrastructure\Persistence\SQLite\UserRepository;
use App\Domain\Entity\User;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for GetSessionUseCase
 *
 * Tests user session retrieval by userId.
 */
class GetSessionUseCaseTest extends IntegrationTestCase
{
    private GetSessionUseCase $useCase;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userRepository = new UserRepository();
        $this->useCase = new GetSessionUseCase($this->userRepository);
    }

    public function testGetSessionReturnsUserResponseForValidUserId(): void
    {
        // Create and save a user
        $user = new User(
            email: 'session@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'crew',
            isAdmin: false
        );
        $this->userRepository->save($user);
        $userId = $user->getId();

        // Get session
        $userResponse = $this->useCase->execute($userId);

        // Verify response data
        $this->assertEquals('session@example.com', $userResponse->email);
        $this->assertEquals('crew', $userResponse->accountType);
        $this->assertFalse($userResponse->isAdmin);
    }

    public function testGetSessionWithBoatOwnerAccountReturnsCorrectType(): void
    {
        // Create boat owner user
        $user = new User(
            email: 'boatowner@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'boat_owner',
            isAdmin: false
        );
        $this->userRepository->save($user);
        $userId = $user->getId();

        // Get session
        $userResponse = $this->useCase->execute($userId);

        // Verify account type
        $this->assertEquals('boat_owner', $userResponse->accountType);
    }

    public function testGetSessionWithAdminUserReturnsAdminFlag(): void
    {
        // Create admin user
        $user = new User(
            email: 'admin@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'crew',
            isAdmin: true
        );
        $this->userRepository->save($user);
        $userId = $user->getId();

        // Get session
        $userResponse = $this->useCase->execute($userId);

        // Verify admin flag
        $this->assertTrue($userResponse->isAdmin);
    }

    public function testGetSessionWithNonExistentUserThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->useCase->execute(99999);
    }

    public function testGetSessionPreservesUserData(): void
    {
        // Create user with specific data
        $user = new User(
            email: 'preservation@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'boat_owner',
            isAdmin: true
        );
        $user->updateLastLogin(new \DateTimeImmutable('2026-02-01 15:30:00'));
        $this->userRepository->save($user);
        $userId = $user->getId();

        // Get session
        $userResponse = $this->useCase->execute($userId);

        // Verify all data preserved
        $this->assertEquals('preservation@example.com', $userResponse->email);
        $this->assertEquals('boat_owner', $userResponse->accountType);
        $this->assertTrue($userResponse->isAdmin);
    }

    public function testGetSessionForMultipleUsers(): void
    {
        // Create multiple users
        $user1 = new User(
            email: 'user1@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'crew',
            isAdmin: false
        );
        $this->userRepository->save($user1);

        $user2 = new User(
            email: 'user2@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'boat_owner',
            isAdmin: true
        );
        $this->userRepository->save($user2);

        // Get sessions for both users
        $response1 = $this->useCase->execute($user1->getId());
        $response2 = $this->useCase->execute($user2->getId());

        // Verify each session contains correct data
        $this->assertEquals('user1@example.com', $response1->email);
        $this->assertEquals('crew', $response1->accountType);
        $this->assertFalse($response1->isAdmin);

        $this->assertEquals('user2@example.com', $response2->email);
        $this->assertEquals('boat_owner', $response2->accountType);
        $this->assertTrue($response2->isAdmin);
    }

    public function testGetSessionWithDeletedUserReturnsNotFoundException(): void
    {
        // Create and save user
        $user = new User(
            email: 'deleme@example.com',
            passwordHash: password_hash('password123', PASSWORD_BCRYPT),
            accountType: 'crew',
            isAdmin: false
        );
        $this->userRepository->save($user);
        $userId = $user->getId();

        // Delete the user
        $this->userRepository->delete($userId);

        // Try to get session
        $this->expectException(\RuntimeException::class);

        $this->useCase->execute($userId);
    }
}
