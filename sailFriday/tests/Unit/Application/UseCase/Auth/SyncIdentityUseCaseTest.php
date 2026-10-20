<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase\Auth;

use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\UseCase\Auth\SyncIdentityUseCase;
use App\Domain\Entity\Crew;
use App\Domain\Entity\User;
use App\Domain\Enum\SkillLevel;
use App\Domain\ValueObject\CrewKey;
use PHPUnit\Framework\TestCase;

class SyncIdentityUseCaseTest extends TestCase
{
    private UserRepositoryInterface $users;
    private CrewRepositoryInterface $crews;
    private SyncIdentityUseCase $useCase;

    protected function setUp(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->crews = $this->createMock(CrewRepositoryInterface::class);
        $this->useCase = new SyncIdentityUseCase($this->users, $this->crews);
    }

    private function user(string $email = 'a@example.com', string $type = 'crew', int $id = 7): User
    {
        $user = new User(email: $email, passwordHash: '', accountType: $type, authUserId: 'auth-1');
        $user->setId($id);
        return $user;
    }

    private function crew(?string $membershipNumber): Crew
    {
        return new Crew(
            key: CrewKey::fromString('jane-doe'),
            displayName: 'JaneD',
            firstName: 'Jane',
            lastName: 'Doe',
            partnerKey: null,
            mobile: null,
            socialPreference: false,
            membershipNumber: $membershipNumber,
            skill: SkillLevel::NOVICE,
            experience: null,
        );
    }

    public function testNothingChangesWhenIdentityMatches(): void
    {
        $this->users->expects($this->never())->method('save');
        $this->crews->method('findByUserId')->willReturn($this->crew('7001-1234'));
        $this->crews->expects($this->never())->method('save');

        $changed = $this->useCase->execute($this->user(), [
            'email_address' => 'A@example.com', 'is_member' => 1, 'membership_number' => '7001-1234',
        ]);

        $this->assertFalse($changed);
    }

    public function testEmailChangeIsCopiedToTheLocalUser(): void
    {
        $user = $this->user();
        $this->users->method('findByEmail')->willReturn(null);
        $this->users->expects($this->once())->method('save')->with($user);

        $changed = $this->useCase->execute($user, ['email_address' => 'new@example.com', 'is_member' => 0]);

        $this->assertTrue($changed);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testEmailIsNotCopiedWhenAnotherLocalUserHasIt(): void
    {
        $user = $this->user();
        $other = $this->user('taken@example.com', 'crew', 8);
        $this->users->method('findByEmail')->willReturn($other);
        $this->users->expects($this->never())->method('save');

        $this->useCase->execute($user, ['email_address' => 'taken@example.com', 'is_member' => 0]);

        $this->assertSame('a@example.com', $user->getEmail());
    }

    public function testNewMembershipNumberIsCopiedToTheCrew(): void
    {
        $crew = $this->crew(null);
        $this->crews->method('findByUserId')->willReturn($crew);
        $this->crews->expects($this->once())->method('save')->with($crew);

        $changed = $this->useCase->execute($this->user(), [
            'email_address' => 'a@example.com', 'is_member' => 1, 'membership_number' => '7002-9999',
        ]);

        $this->assertTrue($changed);
        $this->assertSame('7002-9999', $crew->getMembershipNumber());
    }

    public function testLeavingTheClubClearsTheCrewMembershipNumber(): void
    {
        $crew = $this->crew('7001-1234');
        $this->crews->method('findByUserId')->willReturn($crew);
        $this->crews->expects($this->once())->method('save');

        $this->useCase->execute($this->user(), [
            'email_address' => 'a@example.com', 'is_member' => 0, 'membership_number' => null,
        ]);

        $this->assertNull($crew->getMembershipNumber());
    }

    public function testBoatOwnersHaveNoMembershipSync(): void
    {
        $this->crews->expects($this->never())->method('findByUserId');

        $this->useCase->execute($this->user('a@example.com', 'boat_owner'), [
            'email_address' => 'a@example.com', 'is_member' => 1, 'membership_number' => '7001-1234',
        ]);
    }
}
