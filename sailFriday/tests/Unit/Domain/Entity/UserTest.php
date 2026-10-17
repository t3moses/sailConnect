<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function createUser(
        string $email = 'john.doe@example.com',
        string $passwordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        string $accountType = 'crew',
        bool $isAdmin = false
    ): User {
        return new User(
            email: $email,
            passwordHash: $passwordHash,
            accountType: $accountType,
            isAdmin: $isAdmin
        );
    }

    public function testConstructorSetsProperties(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertEquals('john.doe@example.com', $user->getEmail());
        $this->assertEquals('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', $user->getPasswordHash());
        $this->assertEquals('crew', $user->getAccountType());
        $this->assertFalse($user->isAdmin());
    }

    public function testConstructorInitializesTimestamps(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getUpdatedAt());
        $this->assertNull($user->getLastLogin());
    }

    public function testConstructorAcceptsCustomTimestamps(): void
    {
        // Arrange
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 12:00:00');

        $user = new User(
            email: 'test@example.com',
            passwordHash: 'hash123',
            accountType: 'crew',
            isAdmin: false,
            lastLogin: null,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        // Assert
        $this->assertEquals($createdAt, $user->getCreatedAt());
        $this->assertEquals($updatedAt, $user->getUpdatedAt());
    }

    public function testIdStartsAsNull(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertNull($user->getId());
    }

    public function testSetIdUpdatesId(): void
    {
        // Arrange
        $user = $this->createUser();
        $user->setId(42);

        // Assert
        $this->assertEquals(42, $user->getId());
    }

    public function testSetIdThrowsExceptionWhenAlreadySet(): void
    {
        // Arrange
        $user = $this->createUser();
        $user->setId(42);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User ID is already set');

        $user->setId(43);
    }

    public function testSetEmailUpdatesEmail(): void
    {
        // Arrange
        $user = $this->createUser();

        $user->setEmail('newemail@example.com');

        // Assert
        $this->assertEquals('newemail@example.com', $user->getEmail());
    }

    public function testSetEmailUpdatesTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->setEmail('newemail@example.com');

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testSetEmailValidatesEmail(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $user->setEmail('invalid-email');
    }

    public function testConstructorThrowsExceptionForEmptyEmail(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email cannot be empty');

        new User(
            email: '',
            passwordHash: 'hash123',
            accountType: 'crew'
        );
    }

    public function testConstructorThrowsExceptionForInvalidEmail(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        new User(
            email: 'not-an-email',
            passwordHash: 'hash123',
            accountType: 'crew'
        );
    }

    public function testSetPasswordHashUpdatesHash(): void
    {
        // Arrange
        $user = $this->createUser();

        $newHash = '$2y$10$newHashValue';
        $user->setPasswordHash($newHash);

        // Assert
        $this->assertEquals($newHash, $user->getPasswordHash());
    }

    public function testSetPasswordHashUpdatesTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->setPasswordHash('$2y$10$newHashValue');

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testSetPasswordHashThrowsExceptionForEmptyHash(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password hash cannot be empty');

        $user->setPasswordHash('');
    }

    public function testSetAccountTypeUpdatesType(): void
    {
        // Arrange
        $user = $this->createUser();

        $user->setAccountType('boat_owner');

        // Assert
        $this->assertEquals('boat_owner', $user->getAccountType());
    }

    public function testSetAccountTypeUpdatesTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->setAccountType('boat_owner');

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testConstructorThrowsExceptionForInvalidAccountType(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid account type: invalid. Must be one of: crew, boat_owner');

        new User(
            email: 'test@example.com',
            passwordHash: 'hash123',
            accountType: 'invalid'
        );
    }

    public function testSetAccountTypeThrowsExceptionForInvalidType(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid account type: admin. Must be one of: crew, boat_owner');

        $user->setAccountType('admin');
    }

    public function testSetIsAdminUpdatesStatus(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertFalse($user->isAdmin());

        $user->setIsAdmin(true);

        $this->assertTrue($user->isAdmin());
    }

    public function testSetIsAdminUpdatesTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->setIsAdmin(true);

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testCanManageCrewReturnsTrueForCrewAccount(): void
    {
        // Arrange
        $user = $this->createUser(accountType: 'crew');

        // Assert
        $this->assertTrue($user->canManageCrew());
    }

    public function testCanManageCrewReturnsFalseForBoatOwnerAccount(): void
    {
        // Arrange
        $user = $this->createUser(accountType: 'boat_owner');

        // Assert
        $this->assertFalse($user->canManageCrew());
    }

    public function testCanManageBoatReturnsTrueForBoatOwnerAccount(): void
    {
        // Arrange
        $user = $this->createUser(accountType: 'boat_owner');

        // Assert
        $this->assertTrue($user->canManageBoat());
    }

    public function testCanManageBoatReturnsFalseForCrewAccount(): void
    {
        // Arrange
        $user = $this->createUser(accountType: 'crew');

        // Assert
        $this->assertFalse($user->canManageBoat());
    }

    public function testUpdateLastLoginSetsTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertNull($user->getLastLogin());

        $loginTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $user->updateLastLogin($loginTime);

        $this->assertEquals($loginTime, $user->getLastLogin());
    }

    public function testUpdateLastLoginUpdatesTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->updateLastLogin(new \DateTimeImmutable());

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testToArrayReturnsCompleteArray(): void
    {
        // Arrange
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 12:00:00');
        $lastLogin = new \DateTimeImmutable('2024-01-15 10:30:00');

        $user = new User(
            email: 'test@example.com',
            passwordHash: 'hash123',
            accountType: 'crew',
            isAdmin: true,
            lastLogin: $lastLogin,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
        $user->setId(42);

        $array = $user->toArray();

        // Assert
        $this->assertEquals(42, $array['id']);
        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('crew', $array['account_type']);
        $this->assertTrue($array['is_admin']);
        $this->assertEquals('2024-01-15 10:30:00', $array['last_login']);
        $this->assertEquals('2024-01-01 12:00:00', $array['created_at']);
        $this->assertEquals('2024-01-02 12:00:00', $array['updated_at']);
    }

    public function testToArrayWithNullLastLogin(): void
    {
        // Arrange
        $user = $this->createUser();
        $user->setId(1);

        $array = $user->toArray();

        // Assert
        $this->assertNull($array['last_login']);
    }

    public function testToArrayWithNullId(): void
    {
        // Arrange
        $user = $this->createUser();

        $array = $user->toArray();

        // Assert
        $this->assertNull($array['id']);
    }

    public function testCrewAccountTypeIsValid(): void
    {
        // Arrange
        $user = $this->createUser(accountType: 'crew');

        // Assert
        $this->assertEquals('crew', $user->getAccountType());
    }

    public function testBoatOwnerAccountTypeIsValid(): void
    {
        // Arrange
        $user = $this->createUser(accountType: 'boat_owner');

        // Assert
        $this->assertEquals('boat_owner', $user->getAccountType());
    }

    public function testAdminUserCanBeCreated(): void
    {
        // Arrange
        $user = $this->createUser(isAdmin: true);

        // Assert
        $this->assertTrue($user->isAdmin());
    }

    public function testNonAdminUserCanBecomeAdmin(): void
    {
        // Arrange
        $user = $this->createUser(isAdmin: false);

        // Assert
        $this->assertFalse($user->isAdmin());

        $user->setIsAdmin(true);

        $this->assertTrue($user->isAdmin());
    }

    public function testAdminUserCanBecomeNonAdmin(): void
    {
        // Arrange
        $user = $this->createUser(isAdmin: true);

        // Assert
        $this->assertTrue($user->isAdmin());

        $user->setIsAdmin(false);

        $this->assertFalse($user->isAdmin());
    }

    public function testGetLastLogoutReturnsNullByDefault(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertNull($user->getLastLogout());
    }

    public function testUpdateLastLogoutSetsTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();

        // Assert
        $this->assertNull($user->getLastLogout());

        $logoutTime = new \DateTimeImmutable('2024-01-15 14:30:00');
        $user->updateLastLogout($logoutTime);

        $this->assertEquals($logoutTime, $user->getLastLogout());
    }

    public function testUpdateLastLogoutUpdatesTimestamp(): void
    {
        // Arrange
        $user = $this->createUser();
        $originalUpdatedAt = $user->getUpdatedAt();

        sleep(1);
        $user->updateLastLogout(new \DateTimeImmutable());

        // Assert
        $this->assertGreaterThan($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testConstructorAcceptsLastLogout(): void
    {
        // Arrange
        $lastLogout = new \DateTimeImmutable('2024-01-15 14:30:00');

        $user = new User(
            email: 'test@example.com',
            passwordHash: 'hash123',
            accountType: 'crew',
            isAdmin: false,
            lastLogin: null,
            lastLogout: $lastLogout
        );

        // Assert
        $this->assertEquals($lastLogout, $user->getLastLogout());
    }

    public function testToArrayIncludesLastLogout(): void
    {
        // Arrange
        $lastLogout = new \DateTimeImmutable('2024-01-15 14:30:00');

        $user = new User(
            email: 'test@example.com',
            passwordHash: 'hash123',
            accountType: 'crew',
            isAdmin: false,
            lastLogin: null,
            lastLogout: $lastLogout
        );
        $user->setId(42);

        $array = $user->toArray();

        // Assert
        $this->assertEquals('2024-01-15 14:30:00', $array['last_logout']);
    }

    public function testToArrayWithNullLastLogout(): void
    {
        // Arrange
        $user = $this->createUser();
        $user->setId(1);

        $array = $user->toArray();

        // Assert
        $this->assertNull($array['last_logout']);
    }
}
