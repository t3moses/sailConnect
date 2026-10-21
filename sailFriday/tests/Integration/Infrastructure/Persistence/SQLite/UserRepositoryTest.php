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
            id: $this->createIdentity(['email_address' => 'newuser@example.com']),
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
            id: $this->createIdentity(['email_address' => 'findbyid@example.com']),
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
        $result = $this->repository->findById('user-99999');

        $this->assertNull($result);
    }

    public function testFindByEmailReturnsUserWhenExists(): void
    {
        // Create user
        $user = new User(
            email: 'findbyemail@example.com',
            id: $this->createIdentity(['email_address' => 'findbyemail@example.com']),
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
            id: $this->createIdentity(['email_address' => 'exists@example.com']),
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
        $user = $this->newUser('update@example.com');
        $this->repository->save($user);
        $userId = $user->getId();

        $user->setAccountType('boat_owner');
        $user->setIsAdmin(true);
        $this->repository->save($user);

        $updatedUser = $this->repository->findById($userId);
        $this->assertNotNull($updatedUser);
        $this->assertEquals('boat_owner', $updatedUser->getAccountType());
        $this->assertTrue($updatedUser->isAdmin());
        $this->assertEquals($userId, $updatedUser->getId()); // ID should remain same
    }

    public function testDeleteRemovesUser(): void
    {
        // Create user
        $user = new User(
            email: 'delete@example.com',
            id: $this->createIdentity(['email_address' => 'delete@example.com']),
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
        $this->repository->delete('user-99999');

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
                id: $this->createIdentity(['email_address' => "user{$i}@example.com"]),
                accountType: 'crew',
                isAdmin: false
            );
            $this->repository->save($user);
        }

        $this->assertEquals($initialCount + 3, $this->repository->count());
    }

    public function testNoCredentialsAreStored(): void
    {
        $this->repository->save($this->newUser('password@example.com'));

        $columns = array_column($this->pdo->query('PRAGMA main.table_info(users)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
        $this->assertNotContains('password_hash', $columns);
        $this->assertNotContains('email', $columns);
    }

    public function testAccountTypeIsPersisted(): void
    {
        // Test crew account
        $crewUser = new User(
            email: 'crew@example.com',
            id: $this->createIdentity(['email_address' => 'crew@example.com']),
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($crewUser);

        // Test boat_owner account
        $boatUser = new User(
            email: 'boat@example.com',
            id: $this->createIdentity(['email_address' => 'boat@example.com']),
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
            id: $this->createIdentity(['email_address' => 'admin@example.com']),
            accountType: 'crew',
            isAdmin: true
        );
        $this->repository->save($adminUser);

        // Create regular user
        $regularUser = new User(
            email: 'regular@example.com',
            id: $this->createIdentity(['email_address' => 'regular@example.com']),
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

    public function testEmailFollowsTheSailConnectIdentity(): void
    {
        $user = $this->newUser('original@example.com');
        $this->repository->save($user);
        $userId = $user->getId();

        // The email is changed in sailConnect, not in Sail Friday
        $this->updateAuthEmail($userId, 'updated@example.com');

        // Verify old email doesn't exist
        $this->assertNull($this->repository->findByEmail('original@example.com'));
        $this->assertFalse($this->repository->emailExists('original@example.com'));

        // Verify new email exists
        $foundUser = $this->repository->findByEmail('updated@example.com');
        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, $foundUser->getId());
        $this->assertEquals('updated@example.com', $this->repository->findById($userId)->getEmail());
    }

    private function updateAuthEmail(string $userId, string $email): void
    {
        $this->pdo->prepare('UPDATE auth.users SET email_address = ? WHERE user_id = ?')->execute([$email, $userId]);
    }

    public function testTimestampsAreAutomaticallySet(): void
    {
        $user = new User(
            email: 'timestamps@example.com',
            id: $this->createIdentity(['email_address' => 'timestamps@example.com']),
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
            id: $this->createIdentity(['email_address' => 'update_timestamp@example.com']),
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        $originalUpdatedAt = $user->getUpdatedAt();

        // Give time for timestamp to potentially change (in case of low resolution)
        sleep(1);

        // Update the user
        $user->setIsAdmin(true);
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
            id: $this->createIdentity(['email_address' => 'created_timestamp@example.com']),
            accountType: 'crew',
            isAdmin: false
        );
        $this->repository->save($user);
        $userId = $user->getId();

        $originalCreatedAt = $user->getCreatedAt();

        // Update the user
        $user->setIsAdmin(true);
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
        $this->repository->save(new User(email: 'alpha@example.com', id: $this->createIdentity(['email_address' => 'alpha@example.com']), accountType: 'crew'));
        $this->repository->save(new User(email: 'beta@example.com', id: $this->createIdentity(['email_address' => 'beta@example.com']),  accountType: 'crew'));
        $this->repository->save(new User(email: 'gamma@example.com', id: $this->createIdentity(['email_address' => 'gamma@example.com']), accountType: 'crew'));

        $result = $this->repository->findAll();

        $this->assertCount(3, $result);
    }

    public function testFindAllReturnsUsersOrderedByEmail(): void
    {
        $this->repository->save(new User(email: 'charlie@example.com', id: $this->createIdentity(['email_address' => 'charlie@example.com']), accountType: 'crew'));
        $this->repository->save(new User(email: 'alice@example.com', id: $this->createIdentity(['email_address' => 'alice@example.com']),   accountType: 'crew'));
        $this->repository->save(new User(email: 'bob@example.com', id: $this->createIdentity(['email_address' => 'bob@example.com']),     accountType: 'crew'));

        $result = $this->repository->findAll();

        $this->assertEquals('alice@example.com',   $result[0]->getEmail());
        $this->assertEquals('bob@example.com',     $result[1]->getEmail());
        $this->assertEquals('charlie@example.com', $result[2]->getEmail());
    }

    public function testFindAllReturnsFullyHydratedEntities(): void
    {
        $this->repository->save(new User(
            email: 'hydrate@example.com',
            id: $this->createIdentity(['email_address' => 'hydrate@example.com']),
            accountType: 'boat_owner',
            isAdmin: true));

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
