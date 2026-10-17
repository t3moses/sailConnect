<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase\Cron;

use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\SeasonRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\EmailServiceInterface;
use App\Application\Port\Service\EmailTemplateServiceInterface;
use App\Application\UseCase\Cron\SendBoatOwnerCrewListUseCase;
use App\Domain\Entity\User;
use App\Domain\ValueObject\EventId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SendBoatOwnerCrewListUseCaseTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;
    private SeasonRepositoryInterface $seasonRepository;
    private UserRepositoryInterface $userRepository;
    private EmailServiceInterface $emailService;
    private EmailTemplateServiceInterface $emailTemplateService;
    private SendBoatOwnerCrewListUseCase $useCase;

    protected function setUp(): void
    {
        $this->eventRepository      = $this->createMock(EventRepositoryInterface::class);
        $this->seasonRepository     = $this->createMock(SeasonRepositoryInterface::class);
        $this->userRepository       = $this->createMock(UserRepositoryInterface::class);
        $this->emailService         = $this->createMock(EmailServiceInterface::class);
        $this->emailTemplateService = $this->createMock(EmailTemplateServiceInterface::class);

        $this->useCase = new SendBoatOwnerCrewListUseCase(
            $this->eventRepository,
            $this->seasonRepository,
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

    private function makeFlotilla(array $crewedBoats = []): array
    {
        return [
            'crewed_boats'   => $crewedBoats,
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];
    }

    private function makeCrew(?string $displayName, string $firstName = 'John', string $lastName = 'Doe'): array
    {
        return [
            'key'          => strtolower("$firstName-$lastName"),
            'display_name' => $displayName,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
        ];
    }

    private function makeCrewedBoat(string $boatName, ?int $ownerUserId, array $crews, string $ownerFirstName = 'Robert'): array
    {
        return [
            'boat' => [
                'key'              => strtolower($boatName),
                'display_name'     => $boatName,
                'owner_first_name' => $ownerFirstName,
                'owner_last_name'  => 'Skipper',
                'owner_user_id'    => $ownerUserId,
            ],
            'crews' => $crews,
        ];
    }

    private function makeUser(int $id, string $email): User
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

    public function testReturnsEarlyWhenEventNotFound(): void
    {
        $this->eventRepository->method('findById')->willReturn(null);
        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testReturnsEarlyWhenFlotillaNull(): void
    {
        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn(null);
        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
    }

    public function testReturnsEarlyWhenNoCrewedBoats(): void
    {
        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn($this->makeFlotilla([]));
        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
    }

    public function testSkipsBoatWithNoOwnerUserId(): void
    {
        $boat = $this->makeCrewedBoat('Sailaway', null, [$this->makeCrew('JohnD')]);

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn($this->makeFlotilla([$boat]));
        $this->userRepository->expects($this->never())->method('findById');
        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('no linked owner account', $result['details'][0]);
    }

    public function testSkipsBoatWhenOwnerUserNotFound(): void
    {
        $boat = $this->makeCrewedBoat('Sailaway', 99, [$this->makeCrew('JohnD')]);

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn($this->makeFlotilla([$boat]));
        $this->userRepository->method('findById')->with(99)->willReturn(null);
        $this->emailService->expects($this->never())->method('send');

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('owner user account not found', $result['details'][0]);
    }

    public function testSendsIndividualEmailToEachBoatOwner(): void
    {
        $boat1 = $this->makeCrewedBoat('Sailaway', 1, [
            $this->makeCrew('JohnD', 'John', 'Doe'),
            $this->makeCrew('JaneS', 'Jane', 'Smith'),
        ]);
        $boat2 = $this->makeCrewedBoat('Windward', 2, [
            $this->makeCrew('BobJ', 'Bob', 'Jones'),
        ], 'Alice');

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn($this->makeFlotilla([$boat1, $boat2]));
        $this->userRepository->method('findById')->willReturnMap([
            [1, $this->makeUser(1, 'robert@example.com')],
            [2, $this->makeUser(2, 'alice@example.com')],
        ]);
        $this->emailTemplateService->method('renderBoatOwnerCrewListNotification')->willReturn('<html/>');

        $this->emailService->expects($this->exactly(2))->method('send')->willReturn(true);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testPassesCrewDisplayNamesToTemplate(): void
    {
        $boat = $this->makeCrewedBoat('Sailaway', 1, [
            $this->makeCrew('JohnD', 'John', 'Doe'),
            $this->makeCrew(null, 'Jane', 'Smith'),  // no display name -> falls back to full name
        ]);

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn($this->makeFlotilla([$boat]));
        $this->userRepository->method('findById')->willReturn($this->makeUser(1, 'robert@example.com'));

        $this->emailTemplateService->expects($this->once())
            ->method('renderBoatOwnerCrewListNotification')
            ->with('Robert', 'Sailaway', ['JohnD', 'Jane Smith'], 'Fri May 29', '2026-05-29', '12:45:00')
            ->willReturn('<html/>');
        $this->emailService->method('send')->willReturn(true);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(1, $result['sent']);
    }

    public function testCountsFailedSendsAsSkipped(): void
    {
        $boat = $this->makeCrewedBoat('Sailaway', 1, [$this->makeCrew('JohnD')]);

        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn($this->makeFlotilla([$boat]));
        $this->userRepository->method('findById')->willReturn($this->makeUser(1, 'robert@example.com'));
        $this->emailTemplateService->method('renderBoatOwnerCrewListNotification')->willReturn('<html/>');
        $this->emailService->method('send')->willReturn(false);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testReturnsCorrectResultShape(): void
    {
        $this->eventRepository->method('findById')->willReturn($this->makeEventData());
        $this->seasonRepository->method('getFlotilla')->willReturn(null);

        $result = $this->useCase->execute(EventId::fromString('Fri May 29'));

        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertIsInt($result['sent']);
        $this->assertIsInt($result['skipped']);
        $this->assertIsArray($result['details']);
    }
}
