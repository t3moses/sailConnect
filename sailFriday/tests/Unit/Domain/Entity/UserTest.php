<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function createUser(
        string $email = 'john.doe@example.com',
        string $accountType = 'crew',
        bool $isAdmin = false
    ): User {
        return new User(
            email: $email,
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
        $user->setId('user-42');

        // Assert
        $this->assertEquals('user-42', $user->getId());
    }

    public function testSetIdThrowsExceptionWhenAlreadySet(): void
    {
        // Arrange
        $user = $this->createUser();
        $user->setId('user-42');

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User ID is already set');

        $user->setId('user-43');
    }

    public function testConstructorThrowsExceptionForEmptyEmail(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email cannot be empty');

        new User(
            email: '',
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
            accountType: 'crew'
        );
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
}
