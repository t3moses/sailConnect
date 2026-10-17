<?php

declare(strict_types=1);

namespace Tests\Integration\Application\UseCase\Crew;

use App\Application\Exception\CrewNotFoundException;
use App\Application\Exception\ValidationException;
use App\Application\UseCase\Crew\RecordNoShowUseCase;
use App\Domain\Enum\AvailabilityStatus;
use App\Domain\ValueObject\CrewKey;
use App\Domain\ValueObject\EventId;
use App\Infrastructure\Persistence\SQLite\CrewRepository;
use App\Infrastructure\Persistence\SQLite\EventRepository;
use App\Infrastructure\Persistence\SQLite\SeasonRepository;
use App\Infrastructure\Service\DatabaseTransactionService;
use App\Infrastructure\Service\SystemTimeService;
use Psr\Log\NullLogger;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for RecordNoShowUseCase
 *
 * Shared by the boat-owner flag flow and the admin no-show flow: records a
 * no-show, recomputes commitment rank as initial_commitment_rank minus the
 * crew's total no_shows count (floored at 0), withdraws the crew from every
 * future event, and deactivates the account once commitment rank hits 0.
 */
class RecordNoShowUseCaseTest extends IntegrationTestCase
{
    private RecordNoShowUseCase $useCase;
    private CrewRepository $crewRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulated "now" (set in IntegrationTestCase::initializeSeasonConfig) is
        // 2026-05-01.
        $this->createTestEvent('Fri Apr 17', '2026-04-17');
        $this->createTestEvent('Fri Apr 24', '2026-04-24');
        $this->createTestEvent('Fri May 15', '2026-05-15');

        $this->crewRepository = new CrewRepository();
        $seasonRepository = new SeasonRepository();
        $eventRepository = new EventRepository(new SystemTimeService($seasonRepository));

        $this->useCase = new RecordNoShowUseCase(
            $this->crewRepository,
            $eventRepository,
            new DatabaseTransactionService(),
            new NullLogger()
        );
    }

    // ==================== HELPER METHODS ====================

    protected function createCrewProfileForUser(int $userId, array $overrides = []): string
    {
        $key = $overrides['key'] ?? 'crew_' . $userId;
        $initialCommitmentRank = $overrides['initialCommitmentRank'] ?? 2;

        $stmt = $this->pdo->prepare('
            INSERT INTO crews (
                key, display_name, first_name, last_name, skill,
                commitment_rank, initial_commitment_rank, user_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            $key,
            $overrides['displayName'] ?? 'Test Crew',
            'Test',
            'Crew',
            1,
            $initialCommitmentRank,
            $initialCommitmentRank,
            $userId,
        ]);

        return $key;
    }

    protected function getCommitmentRank(string $crewKey): int
    {
        $stmt = $this->pdo->prepare('SELECT commitment_rank FROM crews WHERE key = ?');
        $stmt->execute([$crewKey]);
        return (int) $stmt->fetchColumn();
    }

    protected function getIsActive(string $crewKey): bool
    {
        $stmt = $this->pdo->prepare('SELECT active FROM crews WHERE key = ?');
        $stmt->execute([$crewKey]);
        return (bool) $stmt->fetchColumn();
    }

    protected function hasAvailabilityRecord(string $crewKey, string $eventId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM crew_availability ca
            JOIN crews c ON c.id = ca.crew_id
            WHERE c.key = ? AND ca.event_id = ?
        ');
        $stmt->execute([$crewKey, $eventId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    protected function countNoShows(string $crewKey): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM no_shows ns
            JOIN crews c ON c.id = ns.crew_id
            WHERE c.key = ?
        ');
        $stmt->execute([$crewKey]);
        return (int) $stmt->fetchColumn();
    }

    // ==================== TESTS ====================

    public function testRecordsNoShowRowAndDerivesCommitmentRank(): void
    {
        $userId = $this->createTestUser('crew1@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId);

        $result = $this->useCase->execute($crewKey, 'Fri Apr 17');

        $this->assertEquals($crewKey, $result['crew_key']);
        $this->assertEquals(1, $result['no_show_count']);
        $this->assertEquals(1, $result['rank_commitment']);
        $this->assertTrue($result['active']);
        $this->assertEquals(1, $this->countNoShows($crewKey));
        $this->assertEquals(1, $this->getCommitmentRank($crewKey));
    }

    public function testSecondNoShowForDifferentEventDecrementsFurther(): void
    {
        $userId = $this->createTestUser('crew2@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId);

        $this->useCase->execute($crewKey, 'Fri Apr 17');
        $result = $this->useCase->execute($crewKey, 'Fri Apr 24');

        $this->assertEquals(2, $result['no_show_count']);
        $this->assertEquals(0, $result['rank_commitment']);
        $this->assertEquals(0, $this->getCommitmentRank($crewKey));
    }

    public function testRankIsFlooredAtZero(): void
    {
        $userId = $this->createTestUser('crew3@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId, ['initialCommitmentRank' => 1]);

        $this->useCase->execute($crewKey, 'Fri Apr 17');
        $result = $this->useCase->execute($crewKey, 'Fri Apr 24');

        // initial 1, 2 no-shows: max(0, 1 - 2) = 0, not negative.
        $this->assertEquals(0, $result['rank_commitment']);
        $this->assertEquals(0, $this->getCommitmentRank($crewKey));
    }

    public function testDeactivatesAccountWhenRankHitsZero(): void
    {
        $userId = $this->createTestUser('crew4@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId, ['initialCommitmentRank' => 1]);

        $result = $this->useCase->execute($crewKey, 'Fri Apr 17');

        $this->assertEquals(0, $result['rank_commitment']);
        $this->assertFalse($result['active']);
        $this->assertFalse($this->getIsActive($crewKey));
    }

    public function testStaysActiveWhenRankAboveZero(): void
    {
        $userId = $this->createTestUser('crew5@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId, ['initialCommitmentRank' => 2]);

        $result = $this->useCase->execute($crewKey, 'Fri Apr 17');

        $this->assertEquals(1, $result['rank_commitment']);
        $this->assertTrue($result['active']);
        $this->assertTrue($this->getIsActive($crewKey));
    }

    public function testWithdrawsCrewFromFutureEvents(): void
    {
        $userId = $this->createTestUser('crew6@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId);

        $this->crewRepository->updateAvailability(
            CrewKey::fromString($crewKey),
            EventId::fromString('Fri May 15'),
            AvailabilityStatus::NOT_SELECTED
        );
        $this->assertTrue($this->hasAvailabilityRecord($crewKey, 'Fri May 15'));

        $result = $this->useCase->execute($crewKey, 'Fri Apr 17');

        $this->assertTrue($result['withdrawn_from_future_events']);
        $this->assertFalse($this->hasAvailabilityRecord($crewKey, 'Fri May 15'));
    }

    public function testDuplicateNoShowForSameCrewAndEventIsIdempotent(): void
    {
        $userId = $this->createTestUser('crew7@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId);

        $this->useCase->execute($crewKey, 'Fri Apr 17');
        $result = $this->useCase->execute($crewKey, 'Fri Apr 17');

        $this->assertEquals(1, $this->countNoShows($crewKey));
        $this->assertEquals(1, $result['no_show_count']);
        $this->assertEquals(1, $result['rank_commitment']);
    }

    public function testRejectsNonPastEventId(): void
    {
        $userId = $this->createTestUser('crew8@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId);

        $this->expectException(ValidationException::class);

        $this->useCase->execute($crewKey, 'Fri May 15');
    }

    public function testRejectsUnknownEventId(): void
    {
        $userId = $this->createTestUser('crew9@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($userId);

        $this->expectException(ValidationException::class);

        $this->useCase->execute($crewKey, 'Nonexistent Event');
    }

    public function testThrowsCrewNotFoundException(): void
    {
        $this->expectException(CrewNotFoundException::class);

        $this->useCase->execute('nonexistent_crew', 'Fri Apr 17');
    }
}
