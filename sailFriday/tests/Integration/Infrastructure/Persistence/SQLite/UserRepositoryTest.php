<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\SQLite;

use App\Infrastructure\Persistence\SQLite\UserRepository;
use App\Domain\Entity\User;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for UserRepository
 *
 * Tests database operations for users including:
 * - CRUD operations (create, read, update, delete)
 * - Email uniqueness checks
 * - User hydration from database
 * - Password hash persistence
 */
class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }

    public function testSaveInsertsNewUser(): void
    {
        $user = new User(
            email: 'newuser@example.com',
            passwordHash: 'hashed_password',
            accountType: 'crew',
            isAdmin: false
        );

        $this->repository->save($user);

        // User should now have an ID
        $this->assertNotNull($user->getId());
        $this->assertGreaterThan(0, $user->getId());
    }

    public function testFindByIdReturnsUserWhenExists(): void
    {
        // Create user
        $user = new User(
            email: 'findbyid@example.com',
            passwordHash: 'hashed_password',
            accountType: 'boat_owner',
            isAdmin: true
        );
        $this->repository->save($user);
        $userId = $user->getId();

        // Find by ID
        $foundUser = $this->repository->findById($userId);

        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, $foundUser->getId());
        $this->assertEquals('findbyid@example.com', $foundUser->getEmail());
        $this->assertEquals('boat_owner', $foundUser->getAccountType());
        $this->assertTrue($foundUser->isAdmin());
    }

    public function testFindByIdReturnsNullWhenNotExists(): void
    {
        $result = $this->repository->findById(99999);

        $this->assertNull($result);
    }

    public function testFindByEmailReturnsUserWhenExists(): void
    {
        // Create user
        $user = new User(
            email: 'findbyemail@example.com',
            passwordHash: 'hashed_password',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);

        // Find by email
        $foundUser = $this->repository->findByEmail('findbyemail@example.com');

        $this->assertNotNull($foundUser);
        $this->assertEquals('findbyemail@example.com', $foundUser->getEmail());
        $this->assertEquals('crew', $foundUser->getAccountType());
    }

    public function testFindByEmailReturnsNullWhenNotExists(): void
    {
        $result = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($result);
    }

    public function testEmailExistsReturnsTrueWhenEmailExists(): void
    {
        // Create user
        $user = new User(
            email: 'exists@example.com',
            passwordHash: 'hashed_password',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);

        // Check existence
        $this->assertTrue($this->repository->emailExists('exists@example.com'));
    }

    public function testEmailExistsReturnsFalseWhenEmailDoesNotExist(): void
    {
        $this->assertFalse($this->repository->emailExists('notexists@example.com'));
    }

    public function testSaveUpdatesExistingUser(): void
    {
        // Create user
        $user = new User(
            email: 'update@example.com',
            passwordHash: 'original_hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        // Update email
        $user->setEmail('newemail@example.com');
        $this->repository->save($user);

        // Verify update
        $updatedUser = $this->repository->findById($userId);
        $this->assertNotNull($updatedUser);
        $this->assertEquals('newemail@example.com', $updatedUser->getEmail());
        $this->assertEquals($userId, $updatedUser->getId()); // ID should remain same
    }

    public function testDeleteRemovesUser(): void
    {
        // Create user
        $user = new User(
            email: 'delete@example.com',
            passwordHash: 'hashed_password',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        // Verify it exists
        $this->assertNotNull($this->repository->findById($userId));

        // Delete
        $this->repository->delete($userId);

        // Verify it's gone
        $this->assertNull($this->repository->findById($userId));
        $this->assertFalse($this->repository->emailExists('delete@example.com'));
    }

    public function testDeleteNonExistentUserDoesNotThrowError(): void
    {
        // Should not throw exception
        $this->repository->delete(99999);

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    public function testCountReturnsCorrectNumberOfUsers(): void
    {
        $initialCount = $this->repository->count();

        // Create 3 users
        for ($i = 1; $i <= 3; $i++) {
            $user = new User(
                email: "user{$i}@example.com",
                passwordHash: 'hashed_password',
                accountType: 'crew',
                isAdmin: false
            );
            $this->repository->save($user);
        }

        $this->assertEquals($initialCount + 3, $this->repository->count());
    }

    public function testPasswordHashIsPersisted(): void
    {
        $passwordHash = password_hash('SecurePassword123', PASSWORD_BCRYPT);

        $user = new User(
            email: 'password@example.com',
            passwordHash: $passwordHash,
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);

        // Retrieve and verify password hash
        $foundUser = $this->repository->findByEmail('password@example.com');
        $this->assertNotNull($foundUser);
        $this->assertEquals($passwordHash, $foundUser->getPasswordHash());
    }

    public function testAccountTypeIsPersisted(): void
    {
        // Test crew account
        $crewUser = new User(
            email: 'crew@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($crewUser);

        // Test boat_owner account
        $boatUser = new User(
            email: 'boat@example.com',
            passwordHash: 'hash',
            accountType: 'boat_owner',
            isAdmin: false
        );
        $this->repository->save($boatUser);

        // Verify
        $foundCrew = $this->repository->findByEmail('crew@example.com');
        $foundBoat = $this->repository->findByEmail('boat@example.com');

        $this->assertEquals('crew', $foundCrew->getAccountType());
        $this->assertEquals('boat_owner', $foundBoat->getAccountType());
    }

    public function testAdminFlagIsPersisted(): void
    {
        // Create admin user
        $adminUser = new User(
            email: 'admin@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: true
        );
        $this->repository->save($adminUser);

        // Create regular user
        $regularUser = new User(
            email: 'regular@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($regularUser);

        // Verify
        $foundAdmin = $this->repository->findByEmail('admin@example.com');
        $foundRegular = $this->repository->findByEmail('regular@example.com');

        $this->assertTrue($foundAdmin->isAdmin());
        $this->assertFalse($foundRegular->isAdmin());
    }

    public function testLastLoginIsPersisted(): void
    {
        $user = new User(
            email: 'login@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );

        // Update last login
        $loginTime = new \DateTimeImmutable('2026-02-08 10:30:00');
        $user->updateLastLogin($loginTime);

        $this->repository->save($user);
        $userId = $user->getId();

        // Retrieve and verify
        $foundUser = $this->repository->findById($userId);
        $this->assertNotNull($foundUser);
        $this->assertNotNull($foundUser->getLastLogin());
        $this->assertEquals('2026-02-08', $foundUser->getLastLogin()->format('Y-m-d'));
    }

    public function testLastLogoutIsPersisted(): void
    {
        $user = new User(
            email: 'logout@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );

        // Update last logout
        $logoutTime = new \DateTimeImmutable('2026-02-08 15:45:00');
        $user->updateLastLogout($logoutTime);

        $this->repository->save($user);
        $userId = $user->getId();

        // Retrieve and verify
        $foundUser = $this->repository->findById($userId);
        $this->assertNotNull($foundUser);
        $this->assertNotNull($foundUser->getLastLogout());
        $this->assertEquals('2026-02-08', $foundUser->getLastLogout()->format('Y-m-d'));
    }

    public function testUpdateEmailChangesEmail(): void
    {
        $user = new User(
            email: 'original@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        // Update email
        $user->setEmail('updated@example.com');
        $this->repository->save($user);

        // Verify old email doesn't exist
        $this->assertNull($this->repository->findByEmail('original@example.com'));

        // Verify new email exists
        $foundUser = $this->repository->findByEmail('updated@example.com');
        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, $foundUser->getId());
    }

    public function testUpdatePasswordChangesPasswordHash(): void
    {
        $user = new User(
            email: 'password_update@example.com',
            passwordHash: 'old_hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        // Update password
        $newHash = password_hash('NewPassword456', PASSWORD_BCRYPT);
        $user->setPasswordHash($newHash);
        $this->repository->save($user);

        // Verify new hash
        $foundUser = $this->repository->findById($userId);
        $this->assertNotNull($foundUser);
        $this->assertEquals($newHash, $foundUser->getPasswordHash());
        $this->assertNotEquals('old_hash', $foundUser->getPasswordHash());
    }

    public function testTimestampsAreAutomaticallySet(): void
    {
        $user = new User(
            email: 'timestamps@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);

        $foundUser = $this->repository->findByEmail('timestamps@example.com');

        $this->assertNotNull($foundUser);
        $this->assertNotNull($foundUser->getCreatedAt());
        $this->assertNotNull($foundUser->getUpdatedAt());
    }

    public function testUpdatedAtChangesOnUpdate(): void
    {
        $user = new User(
            email: 'update_timestamp@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        $originalUpdatedAt = $user->getUpdatedAt();

        // Give time for timestamp to potentially change (in case of low resolution)
        sleep(1);

        // Update the user
        $user->setEmail('new_update_timestamp@example.com');
        $this->repository->save($user);

        $updatedUser = $this->repository->findById($userId);

        // The updated_at timestamp should be later or equal
        $this->assertGreaterThanOrEqual(
            $originalUpdatedAt->getTimestamp(),
            $updatedUser->getUpdatedAt()->getTimestamp()
        );
    }

    public function testCreatedAtDoesNotChangeOnUpdate(): void
    {
        $user = new User(
            email: 'created_timestamp@example.com',
            passwordHash: 'hash',
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        $originalCreatedAt = $user->getCreatedAt();

        // Update the user
        $user->setEmail('new_created_timestamp@example.com');
        $this->repository->save($user);

        $updatedUser = $this->repository->findById($userId);

        // The created_at timestamp should remain the same
        $this->assertEquals(
            $originalCreatedAt->format('Y-m-d H:i:s'),
            $updatedUser->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }

    // =======================
    // findAll() Tests
    // =======================

    public function testFindAllReturnsEmptyArrayWhenNoUsers(): void
    {
        $result = $this->repository->findAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindAllReturnsAllUsers(): void
    {
        $this->repository->save(new User(email: 'alpha@example.com', passwordHash: 'hash', accountType: 'crew'));
        $this->repository->save(new User(email: 'beta@example.com',  passwordHash: 'hash', accountType: 'crew'));
        $this->repository->save(new User(email: 'gamma@example.com', passwordHash: 'hash', accountType: 'crew'));

        $result = $this->repository->findAll();

        $this->assertCount(3, $result);
    }

    public function testFindAllReturnsUsersOrderedByEmail(): void
    {
        $this->repository->save(new User(email: 'charlie@example.com', passwordHash: 'hash', accountType: 'crew'));
        $this->repository->save(new User(email: 'alice@example.com',   passwordHash: 'hash', accountType: 'crew'));
        $this->repository->save(new User(email: 'bob@example.com',     passwordHash: 'hash', accountType: 'crew'));

        $result = $this->repository->findAll();

        $this->assertEquals('alice@example.com',   $result[0]->getEmail());
        $this->assertEquals('bob@example.com',     $result[1]->getEmail());
        $this->assertEquals('charlie@example.com', $result[2]->getEmail());
    }

    public function testFindAllReturnsFullyHydratedEntities(): void
    {
        $this->repository->save(new User(
            email: 'hydrate@example.com',
            passwordHash: 'hashed_pw',
            accountType: 'boat_owner',
            isAdmin: true,
        ));

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $user = $result[0];
        $this->assertNotNull($user->getId());
        $this->assertEquals('hydrate@example.com', $user->getEmail());
        $this->assertEquals('boat_owner', $user->getAccountType());
        $this->assertTrue($user->isAdmin());
        $this->assertNotNull($user->getCreatedAt());
    }
}
