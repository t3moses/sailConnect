<?php

declare(strict_types=1);

namespace Tests\Integration\Application\UseCase\Crew;

use App\Application\UseCase\Crew\GetUserAssignmentsUseCase;
use App\Domain\ValueObject\EventId;
use App\Infrastructure\Persistence\SQLite\BoatRepository;
use App\Infrastructure\Persistence\SQLite\CrewRepository;
use App\Infrastructure\Persistence\SQLite\EventRepository;
use App\Infrastructure\Persistence\SQLite\SeasonRepository;
use App\Infrastructure\Service\SystemTimeService;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for GetUserAssignmentsUseCase
 *
 * Verifies that a boat owner's crewmate entries include the live skill,
 * membership_rank, commitment_rank, and experience fields the "My Boat
 * Assignments" crew-detail modal needs, read fresh from the crew repository
 * rather than the (potentially stale) flotilla JSON snapshot.
 */
class GetUserAssignmentsUseCaseTest extends IntegrationTestCase
{
    private GetUserAssignmentsUseCase $useCase;
    private BoatRepository $boatRepository;
    private CrewRepository $crewRepository;
    private SeasonRepository $seasonRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestEvent('Fri Apr 17', '2026-04-17');

        $this->boatRepository = new BoatRepository();
        $this->crewRepository = new CrewRepository();
        $this->seasonRepository = new SeasonRepository();
        $eventRepository = new EventRepository(new SystemTimeService($this->seasonRepository));

        $this->useCase = new GetUserAssignmentsUseCase(
            $this->crewRepository,
            $this->boatRepository,
            $eventRepository,
            $this->seasonRepository
        );
    }

    protected function createBoatProfileForUser(int $userId, array $overrides = []): string
    {
        $key = $overrides['key'] ?? 'boat_' . $userId;

        $stmt = $this->pdo->prepare('
            INSERT INTO boats (
                key, display_name, owner_first_name, owner_last_name,
                min_berths, max_berths, assistance_required, social_preference,
                owner_user_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            $key,
            $overrides['displayName'] ?? 'Test Boat',
            'Test',
            'Owner',
            $overrides['minBerths'] ?? 1,
            $overrides['maxBerths'] ?? 4,
            'No',
            'No',
            $userId,
        ]);

        return $key;
    }

    protected function createCrewProfileForUser(int $userId, array $overrides = []): string
    {
        $key = $overrides['key'] ?? 'crew_' . $userId;

        $stmt = $this->pdo->prepare('
            INSERT INTO crews (
                key, display_name, first_name, last_name, skill, experience,
                membership_number, commitment_rank, membership_rank, user_id,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            $key,
            $overrides['displayName'] ?? 'Test Crew',
            'Test',
            'Crew',
            $overrides['skill'] ?? 1,
            $overrides['experience'] ?? 'CANSail 3',
            $overrides['membershipNumber'] ?? '123456',
            $overrides['commitmentRank'] ?? 1,
            $overrides['membershipRank'] ?? 1,
            $userId,
        ]);

        return $key;
    }

    protected function saveFlotillaWithCrew(string $eventId, string $boatKey, array $crewKeys): void
    {
        $this->seasonRepository->saveFlotilla(EventId::fromString($eventId), [
            'event_id' => $eventId,
            'crewed_boats' => [
                [
                    'boat' => ['key' => $boatKey, 'display_name' => 'Test Boat'],
                    'crews' => array_map(fn($k) => ['key' => $k, 'display_name' => 'Test Crew', 'skill' => 0], $crewKeys),
                ],
            ],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ]);
    }

    public function testBoatOwnerCrewmatesIncludeLiveSkillMembershipCommitmentAndExperience(): void
    {
        $ownerId = $this->createTestUser('owner1@example.com', 'boat_owner');
        $boatKey = $this->createBoatProfileForUser($ownerId);

        $crewUserId = $this->createTestUser('crew1@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, [
            'skill' => 2,
            'experience' => 'CANSail 4, 3 seasons racing',
            'commitmentRank' => 1,
            'membershipRank' => 1,
        ]);

        // Flotilla snapshot deliberately has a stale skill (0) for this crewmate;
        // the use case should prefer the live value (2) from the crew repository.
        $this->saveFlotillaWithCrew('Fri Apr 17', $boatKey, [$crewKey]);

        $assignments = $this->useCase->execute($ownerId);

        $matched = array_values(array_filter($assignments, fn($a) => $a->eventId === 'Fri Apr 17'));
        $this->assertCount(1, $matched);

        $crewmates = $matched[0]->crewmates;
        $this->assertCount(1, $crewmates);

        $crewmate = $crewmates[0];
        $this->assertEquals($crewKey, $crewmate['key']);
        $this->assertEquals(2, $crewmate['skill']);
        $this->assertEquals(1, $crewmate['membership_rank']);
        $this->assertEquals(1, $crewmate['commitment_rank']);
        $this->assertEquals('CANSail 4, 3 seasons racing', $crewmate['experience']);
    }

    public function testNonMemberCrewHasMembershipRankZero(): void
    {
        $ownerId = $this->createTestUser('owner2@example.com', 'boat_owner');
        $boatKey = $this->createBoatProfileForUser($ownerId);

        $crewUserId = $this->createTestUser('crew2@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, [
            'membershipRank' => 0,
        ]);

        $this->saveFlotillaWithCrew('Fri Apr 17', $boatKey, [$crewKey]);

        $assignments = $this->useCase->execute($ownerId);
        $matched = array_values(array_filter($assignments, fn($a) => $a->eventId === 'Fri Apr 17'));

        $this->assertEquals(0, $matched[0]->crewmates[0]['membership_rank']);
    }
}
