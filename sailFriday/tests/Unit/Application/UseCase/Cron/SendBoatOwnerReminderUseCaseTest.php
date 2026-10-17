<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase\Cron;

use App\Application\Port\Repository\BoatRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\EmailServiceInterface;
use App\Application\Port\Service\EmailTemplateServiceInterface;
use App\Application\UseCase\Cron\SendBoatOwnerReminderUseCase;
use App\Domain\Entity\Boat;
use App\Domain\Entity\User;
use App\Domain\ValueObject\BoatKey;
use App\Domain\ValueObject\EventId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SendBoatOwnerReminderUseCaseTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;
    private BoatRepositoryInterface $boatRepository;
    private UserRepositoryInterface $userRepository;
    private EmailServiceInterface $emailService;
    private EmailTemplateServiceInterface $emailTemplateService;
    private SendBoatOwnerReminderUseCase $useCase;

    protected function setUp(): void
    {
        $this->eventRepository      = $this->createMock(EventRepositoryInterface::class);
        $this->boatRepository       = $this->createMock(BoatRepositoryInterface::class);
        $this->userRepository       = $this->createMock(UserRepositoryInterface::class);
        $this->emailService         = $this->createMock(EmailServiceInterface::class);
        $this->emailTemplateService = $this->createMock(EmailTemplateServiceInterface::class);

        $this->useCase = new SendBoatOwnerReminderUseCase(
            $this->eventRepository,
            $this->boatRepository,
            $this->userRepository,
            $this->emailService,
            $this->emailTemplateService,
            new NullLogger()
        );
    }

    private function makeEventData(): array
    {
        return [
            'event_id'    => 'Fri May 29',
            'event_date'  => '2026-05-29',
            'start_time'  => '12:45:00',
            'finish_time' => '17:00:00',
        ];
    }

    private function makeBoat(string $key = 'sailaway', ?int $ownerUserId = 1): Boat
    {
        $boat = new Boat(
            key: BoatKey::fromString($key),
            displayName: 'Sail Away',
            ownerFirstName: 'Sam',
            ownerLastName: 'Skipper',
            ownerMobile: null,
            minBerths: 2,
            maxBerths: 4,
            assistanceRequired: false,
            socialPreference: false,
        );
        $boat->setOwnerUserId($ownerUserId);
        return $boat;
    }

    private function makeUser(int $id = 1, string $email = 'sam@example.com'): User
    {
        $user = new User(
            email: $email,
            passwordHash: 'hash',
            accountType: 'boat_owner',
            isAdmin: false
        );
        $user->setId($id);
        return $user;
    }

    public function testReturnsEmptyResultWhenEventNotFound(): void
    {
        $this->eventRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNotEmpty($result['details']);
    }

    public function testReturnsEmptyResultWhenNoBoatsRegistered(): void
    {
        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->boatRepository->method('findAvailableForEvent')->willReturn([]);

        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testSkipsBoatWithNoOwnerUserId(): void
    {
        $boat = $this->makeBoat('sailaway', null);

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->boatRepository->method('findAvailableForEvent')->willReturn([$boat]);

        $this->userRepository->expects($this->never())->method('findById');
        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('no linked owner account', $result['details'][0]);
    }

    public function testSkipsBoatWhenOwnerUserNotFound(): void
    {
        $boat = $this->makeBoat('sailaway', 99);

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->boatRepository->method('findAvailableForEvent')->willReturn([$boat]);
        $this->userRepository->method('findById')->with(99)->willReturn(null);

        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('owner user account not found', $result['details'][0]);
    }

    public function testSendsEmailToEachRegisteredBoatOwner(): void
    {
        $boat1 = $this->makeBoat('sailaway', 1);
        $boat2 = $this->makeBoat('windchaser', 2);
        $user1 = $this->makeUser(1, 'sam@example.com');
        $user2 = $this->makeUser(2, 'pat@example.com');

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->boatRepository->method('findAvailableForEvent')->willReturn([$boat1, $boat2]);
        $this->userRepository->method('findById')->willReturnMap([[1, $user1], [2, $user2]]);
        $this->emailTemplateService->method('renderBoatOwnerReminderNotification')
            ->willReturn('<html>reminder</html>');

        $this->emailService->expects($this->exactly(2))
            ->method('send')
            ->willReturn(true);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testCountsFailedSendsAsSkipped(): void
    {
        $boat = $this->makeBoat('sailaway', 1);
        $user = $this->makeUser(1, 'sam@example.com');

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->boatRepository->method('findAvailableForEvent')->willReturn([$boat]);
        $this->userRepository->method('findById')->willReturn($user);
        $this->emailTemplateService->method('renderBoatOwnerReminderNotification')->willReturn('<html/>');
        $this->emailService->method('send')->willReturn(false);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testReturnsCorrectResultShape(): void
    {
        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->boatRepository->method('findAvailableForEvent')->willReturn([]);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertIsInt($result['sent']);
        $this->assertIsInt($result['skipped']);
        $this->assertIsArray($result['details']);
    }
}
