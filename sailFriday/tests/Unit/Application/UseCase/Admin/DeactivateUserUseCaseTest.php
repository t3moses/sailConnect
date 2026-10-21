<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase\Admin;

use App\Application\Exception\ValidationException;
use App\Application\Port\Repository\CrewRepositoryInterface;
use App\Application\Port\Repository\EventRepositoryInterface;
use App\Application\Port\Repository\UserRepositoryInterface;
use App\Application\Port\Service\TransactionServiceInterface;
use App\Application\UseCase\Admin\DeactivateUserUseCase;
use App\Domain\Entity\Crew;
use App\Domain\Entity\User;
use App\Domain\Enum\CrewRankDimension;
use App\Domain\Enum\SkillLevel;
use App\Domain\ValueObject\CrewKey;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeactivateUserUseCaseTest extends TestCase
{
    private function createUser(string $id): User
    {
        $user = new User(
            email: "user{$id}@example.com",
            accountType: 'crew',
            isAdmin: false,
        );
        $user->setId($id);

        return $user;
    }

    private function createCrew(): Crew
    {
        return new Crew(
            key: CrewKey::fromString('janedoe'),
            displayName: 'Jane D.',
            firstName: 'Jane',
            lastName: 'Doe',
            partnerKey: null,
            mobile: null,
            socialPreference: false,
            membershipNumber: null,
            skill: SkillLevel::NOVICE,
            experience: null,
        );
    }

    private function makeUseCase(
        UserRepositoryInterface $userRepository,
        ?CrewRepositoryInterface $crewRepository = null,
        ?EventRepositoryInterface $eventRepository = null,
        ?TransactionServiceInterface $transactionService = null,
    ): DeactivateUserUseCase {
        return new DeactivateUserUseCase(
            $userRepository,
            $crewRepository ?? $this->createMock(CrewRepositoryInterface::class),
            $eventRepository ?? $this->createMock(EventRepositoryInterface::class),
            $transactionService ?? $this->createMock(TransactionServiceInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testThrowsValidationExceptionWhenTargetingOwnAccount(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->never())->method('findById');

        $useCase = $this->makeUseCase($userRepository);

        $this->expectException(ValidationException::class);

        $useCase->execute(targetUserId: 'user-42', requestingUserId: 'user-42');
    }

    public function testThrowsRuntimeExceptionWhenUserNotFound(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findById')
            ->with('user-999')
            ->willReturn(null);

        $useCase = $this->makeUseCase($userRepository);

        $this->expectException(\RuntimeException::class);

        $useCase->execute(targetUserId: 'user-999', requestingUserId: 'user-1');
    }

    public function testThrowsValidationExceptionWhenUserHasNoLinkedCrew(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->with('user-6')->willReturn($this->createUser('user-6'));

        $crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $crewRepository->method('findByUserId')->with('user-6')->willReturn(null);

        $useCase = $this->makeUseCase($userRepository, $crewRepository);

        $this->expectException(ValidationException::class);

        $useCase->execute(targetUserId: 'user-6', requestingUserId: 'user-1');
    }

    public function testDeactivatesCrewWithdrawsFromFutureEventsAndZeroesCommitment(): void
    {
        $crew = $this->createCrew();
        $crew->setRankDimension(CrewRankDimension::COMMITMENT, 2);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->with('user-5')->willReturn($this->createUser('user-5'));

        $crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $crewRepository->method('findByUserId')->with('user-5')->willReturn($crew);
        $crewRepository->expects($this->once())
            ->method('deleteAvailabilityForEvents')
            ->with($crew->getKey(), ['2026-06-01', '2026-06-08']);
        $crewRepository->expects($this->once())->method('updateRankCommitment')->with($crew);
        $crewRepository->expects($this->once())->method('updateActive')->with($crew);
        $crewRepository->expects($this->never())->method('delete');

        $eventRepository = $this->createMock(EventRepositoryInterface::class);
        $eventRepository->method('findFutureEvents')->willReturn(['2026-06-01', '2026-06-08']);

        $transactionService = $this->createMock(TransactionServiceInterface::class);
        $transactionService->expects($this->once())->method('begin');
        $transactionService->expects($this->once())->method('commit');
        $transactionService->expects($this->never())->method('rollBack');

        $useCase = $this->makeUseCase($userRepository, $crewRepository, $eventRepository, $transactionService);

        $result = $useCase->execute(targetUserId: 'user-5', requestingUserId: 'user-1');

        $this->assertFalse($crew->isActive());
        $this->assertSame(0, $crew->getRank()->getDimension(CrewRankDimension::COMMITMENT));
        $this->assertTrue($result['deactivated']);
        $this->assertTrue($result['withdrawn_from_future_events']);
    }

    public function testDeactivatesCrewWhenThereAreNoFutureEvents(): void
    {
        $crew = $this->createCrew();

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->with('user-5')->willReturn($this->createUser('user-5'));

        $crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $crewRepository->method('findByUserId')->with('user-5')->willReturn($crew);
        $crewRepository->expects($this->never())->method('deleteAvailabilityForEvents');
        $crewRepository->expects($this->once())->method('updateRankCommitment')->with($crew);
        $crewRepository->expects($this->once())->method('updateActive')->with($crew);

        $eventRepository = $this->createMock(EventRepositoryInterface::class);
        $eventRepository->method('findFutureEvents')->willReturn([]);

        $useCase = $this->makeUseCase($userRepository, $crewRepository, $eventRepository);

        $result = $useCase->execute(targetUserId: 'user-5', requestingUserId: 'user-1');

        $this->assertFalse($crew->isActive());
        $this->assertSame(0, $crew->getRank()->getDimension(CrewRankDimension::COMMITMENT));
        $this->assertFalse($result['withdrawn_from_future_events']);
    }

    public function testRollsBackTransactionOnFailure(): void
    {
        $crew = $this->createCrew();

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->with('user-7')->willReturn($this->createUser('user-7'));

        $crewRepository = $this->createMock(CrewRepositoryInterface::class);
        $crewRepository->method('findByUserId')->with('user-7')->willReturn($crew);
        $crewRepository->method('updateActive')->willThrowException(new \RuntimeException('db error'));

        $eventRepository = $this->createMock(EventRepositoryInterface::class);
        $eventRepository->method('findFutureEvents')->willReturn([]);

        $transactionService = $this->createMock(TransactionServiceInterface::class);
        $transactionService->expects($this->once())->method('begin');
        $transactionService->expects($this->never())->method('commit');
        $transactionService->expects($this->once())->method('rollBack');

        $useCase = $this->makeUseCase($userRepository, $crewRepository, $eventRepository, $transactionService);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('db error');

        $useCase->execute(targetUserId: 'user-7', requestingUserId: 'user-1');
    }
}
