<?php

declare(strict_types=1);

namespace Tests\Integration\Application\UseCase\Boat;

use App\Application\Exception\BoatNotFoundException;
use App\Application\Exception\ValidationException;
use App\Application\UseCase\Boat\UpdateAssignedCrewSkillUseCase;
use App\Domain\ValueObject\EventId;
use App\Infrastructure\Persistence\SQLite\BoatRepository;
use App\Infrastructure\Persistence\SQLite\CrewRepository;
use App\Infrastructure\Persistence\SQLite\EventRepository;
use App\Infrastructure\Persistence\SQLite\SeasonRepository;
use App\Infrastructure\Service\SystemTimeService;
use Psr\Log\NullLogger;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for UpdateAssignedCrewSkillUseCase
 *
 * Verifies that a boat owner can correct the skill level of a crew member
 * genuinely assigned to their boat for a past event, that the (eventId,
 * crewKey) pair is independently verified against the persisted flotilla,
 * and that only past events are eligible.
 */
class UpdateAssignedCrewSkillUseCaseTest extends IntegrationTestCase
{
    private UpdateAssignedCrewSkillUseCase $useCase;
    private BoatRepository $boatRepository;
    private CrewRepository $crewRepository;
    private SeasonRepository $seasonRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulated "now" (set in IntegrationTestCase::initializeSeasonConfig) is
        // 2026-05-01, so this is a past event — required for the skill correction
        // to be allowed. 'Fri May 15' is in the future.
        $this->createTestEvent('Fri Apr 17', '2026-04-17');
        $this->createTestEvent('Fri May 15', '2026-05-15');

        $this->boatRepository = new BoatRepository();
        $this->crewRepository = new CrewRepository();
        $this->seasonRepository = new SeasonRepository();
        $eventRepository = new EventRepository(new SystemTimeService($this->seasonRepository));

        $this->useCase = new UpdateAssignedCrewSkillUseCase(
            $this->boatRepository,
            $this->crewRepository,
            $eventRepository,
            $this->seasonRepository,
            new NullLogger()
        );
    }

    // ==================== HELPER METHODS ====================

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
                key, display_name, first_name, last_name, skill,
                commitment_rank, user_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            $key,
            $overrides['displayName'] ?? 'Test Crew',
            'Test',
            'Crew',
            $overrides['skill'] ?? 0,
            2,
            $userId,
        ]);

        return $key;
    }

    protected function getSkill(string $crewKey): int
    {
        $stmt = $this->pdo->prepare('SELECT skill FROM crews WHERE key = ?');
        $stmt->execute([$crewKey]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Persist a minimal flotilla where $crewKeys are assigned to $boatKey for $eventId.
     */
    protected function saveFlotillaWithCrew(string $eventId, string $boatKey, array $crewKeys): void
    {
        $this->seasonRepository->saveFlotilla(EventId::fromString($eventId), [
            'event_id' => $eventId,
            'crewed_boats' => [
                [
                    'boat' => ['key' => $boatKey],
                    'crews' => array_map(fn($k) => ['key' => $k], $crewKeys),
                ],
            ],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ]);
    }

    // ==================== HAPPY PATH TESTS ====================

    public function testUpdatesSkillForCrewAssignedToPastEvent(): void
    {
        $ownerId = $this->createTestUser('owner1@example.com', 'boat_owner');
        $boatKey = $this->createBoatProfileForUser($ownerId);
        $crewUserId = $this->createTestUser('crew1@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, ['skill' => 0]);

        $this->saveFlotillaWithCrew('Fri Apr 17', $boatKey, [$crewKey]);

        $result = $this->useCase->execute($ownerId, 'Fri Apr 17', $crewKey, 2);

        $this->assertEquals($crewKey, $result['crew_key']);
        $this->assertEquals(2, $result['skill']);
        $this->assertEquals(2, $this->getSkill($crewKey));
    }

    // ==================== VALIDATION TESTS ====================

    public function testInvalidSkillValueThrowsValidationException(): void
    {
        $ownerId = $this->createTestUser('owner2@example.com', 'boat_owner');
        $boatKey = $this->createBoatProfileForUser($ownerId);
        $crewUserId = $this->createTestUser('crew2@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, ['skill' => 0]);

        $this->saveFlotillaWithCrew('Fri Apr 17', $boatKey, [$crewKey]);

        $this->expectException(ValidationException::class);

        $this->useCase->execute($ownerId, 'Fri Apr 17', $crewKey, 5);
    }

    public function testFutureEventThrowsValidationException(): void
    {
        $ownerId = $this->createTestUser('owner3@example.com', 'boat_owner');
        $boatKey = $this->createBoatProfileForUser($ownerId);
        $crewUserId = $this->createTestUser('crew3@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, ['skill' => 0]);

        // Crew is genuinely assigned, but 'Fri May 15' hasn't happened yet
        $this->saveFlotillaWithCrew('Fri May 15', $boatKey, [$crewKey]);

        $this->expectException(ValidationException::class);

        $this->useCase->execute($ownerId, 'Fri May 15', $crewKey, 2);

        $this->assertEquals(0, $this->getSkill($crewKey));
    }

    // ==================== SECURITY / VERIFICATION TESTS ====================

    public function testCrewNotActuallyAssignedThrowsValidationException(): void
    {
        $ownerId = $this->createTestUser('owner4@example.com', 'boat_owner');
        $boatKey = $this->createBoatProfileForUser($ownerId);
        $crewUserId = $this->createTestUser('crew4@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, ['skill' => 0]);

        // Flotilla exists for the event, but this crew is NOT on the boat
        $this->saveFlotillaWithCrew('Fri Apr 17', $boatKey, []);

        $this->expectException(ValidationException::class);

        $this->useCase->execute($ownerId, 'Fri Apr 17', $crewKey, 2);

        $this->assertEquals(0, $this->getSkill($crewKey));
    }

    public function testCrewAssignedToDifferentBoatThrowsValidationException(): void
    {
        $ownerId = $this->createTestUser('owner5@example.com', 'boat_owner');
        $this->createBoatProfileForUser($ownerId);

        $otherOwnerId = $this->createTestUser('owner5b@example.com', 'boat_owner');
        $otherBoatKey = $this->createBoatProfileForUser($otherOwnerId, ['key' => 'boat_other']);

        $crewUserId = $this->createTestUser('crew5@example.com', 'crew');
        $crewKey = $this->createCrewProfileForUser($crewUserId, ['skill' => 0]);

        // Crew is assigned to the OTHER owner's boat, not this owner's boat
        $this->saveFlotillaWithCrew('Fri Apr 17', $otherBoatKey, [$crewKey]);

        $this->expectException(ValidationException::class);

        $this->useCase->execute($ownerId, 'Fri Apr 17', $crewKey, 2);

        $this->assertEquals(0, $this->getSkill($crewKey));
    }

    // ==================== ERROR CONDITION TESTS ====================

    public function testBoatNotFoundThrowsException(): void
    {
        $nonExistentUserId = 99999;

        $this->expectException(BoatNotFoundException::class);

        $this->useCase->execute($nonExistentUserId, 'Fri Apr 17', 'someone', 2);
    }
}
