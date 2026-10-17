<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\SQLite;

use App\Infrastructure\Persistence\SQLite\SeasonRepository;
use App\Infrastructure\Persistence\SQLite\Connection;
use App\Domain\ValueObject\EventId;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Unit tests for SeasonRepository flotilla persistence
 *
 * Tests the database CRUD operations for flotilla table including:
 * - Insert new flotillas (UPSERT)
 * - Update existing flotillas
 * - JSON serialization/deserialization
 * - Retrieval and deletion
 */
class SeasonRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SeasonRepository $repository;

    protected function setUp(): void
    {
        // Create in-memory database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create events table (required by foreign key)
        $this->pdo->exec("
            CREATE TABLE events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT NOT NULL UNIQUE,
                event_date DATE NOT NULL,
                start_time TIME NOT NULL,
                finish_time TIME NOT NULL,
                status TEXT DEFAULT 'upcoming',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create flotillas table
        $this->pdo->exec("
            CREATE TABLE flotillas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT NOT NULL UNIQUE,
                flotilla_data TEXT NOT NULL,
                generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
            )
        ");

        // Create season_config table (required by SeasonRepository)
        $this->pdo->exec("
            CREATE TABLE season_config (
                id INTEGER PRIMARY KEY,
                year INTEGER NOT NULL,
                source TEXT NOT NULL,
                simulated_date DATE,
                start_time TIME DEFAULT '12:45:00',
                finish_time TIME DEFAULT '17:00:00',
                blackout_from TIME DEFAULT '10:00:00',
                blackout_to TIME DEFAULT '18:00:00',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Set test connection
        Connection::setTestConnection($this->pdo);

        // Initialize season config (required by SeasonRepository)
        $this->pdo->exec("INSERT INTO season_config (id, year, source) VALUES (1, 2025, 'simulated')");

        $this->repository = new SeasonRepository();
    }

    protected function tearDown(): void
    {
        Connection::resetTestConnection();
    }

    /**
     * Helper method to create test flotilla data
     */
    private function createTestFlotilla(string $eventId): array
    {
        return [
            'event_id' => $eventId,
            'crewed_boats' => [
                [
                    'boat' => [
                        'key' => 'sailaway',
                        'display_name' => 'Sail Away',
                        'owner_first_name' => 'John',
                        'owner_last_name' => 'Doe',
                        'min_berths' => 2,
                        'max_berths' => 4,
                    ],
                    'crews' => [
                        [
                            'key' => 'johndoe',
                            'display_name' => 'John Doe',
                            'skill' => 1,
                        ],
                    ],
                ],
            ],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];
    }

    /**
     * Test: saveFlotilla() inserts a new flotilla record into database
     */
    public function testSaveFlotillaInsertsNewRecord(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri May 29');
        $flotillaData = $this->createTestFlotilla('Fri May 29');

        // Act
        // Insert flotilla
        $this->repository->saveFlotilla($eventId, $flotillaData);

        // Query database directly to verify
        $stmt = $this->pdo->prepare('SELECT * FROM flotillas WHERE event_id = ?');
        $stmt->execute(['Fri May 29']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assert
        $this->assertNotFalse($row, 'Flotilla record should exist');
        $this->assertEquals('Fri May 29', $row['event_id']);
        $this->assertNotEmpty($row['flotilla_data']);
        $this->assertNotEmpty($row['generated_at']);

        // Verify JSON is valid
        $decodedData = json_decode($row['flotilla_data'], true);
        $this->assertNotNull($decodedData, 'flotilla_data should be valid JSON');
        $this->assertEquals('Fri May 29', $decodedData['event_id']);
    }

    /**
     * Test: saveFlotilla() updates existing record instead of creating duplicate (UPSERT)
     */
    public function testSaveFlotillaUpdatesExistingRecord(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri May 29');

        // Insert initial flotilla
        $initialData = $this->createTestFlotilla('Fri May 29');
        $this->repository->saveFlotilla($eventId, $initialData);

        // Get initial timestamp
        $stmt = $this->pdo->prepare('SELECT generated_at FROM flotillas WHERE event_id = ?');
        $stmt->execute(['Fri May 29']);
        $initialTimestamp = $stmt->fetchColumn();

        // Small delay to ensure timestamp changes
        sleep(1);

        // Act
        // Update with different data
        $updatedData = $this->createTestFlotilla('Fri May 29');
        $updatedData['crewed_boats'][] = [
            'boat' => ['key' => 'seabreeze', 'display_name' => 'Sea Breeze'],
            'crews' => [],
        ];
        $this->repository->saveFlotilla($eventId, $updatedData);

        // Assert
        // Verify only ONE record exists (no duplicates)
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM flotillas WHERE event_id = ?');
        $stmt->execute(['Fri May 29']);
        $count = $stmt->fetchColumn();
        $this->assertEquals(1, $count, 'Should have exactly one flotilla record');

        // Verify data was updated
        $stmt = $this->pdo->prepare('SELECT flotilla_data, generated_at FROM flotillas WHERE event_id = ?');
        $stmt->execute(['Fri May 29']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $decodedData = json_decode($row['flotilla_data'], true);
        $this->assertCount(2, $decodedData['crewed_boats'], 'Should have 2 crewed boats after update');

        // Verify timestamp was updated
        $this->assertNotEquals($initialTimestamp, $row['generated_at'], 'Timestamp should be updated');
    }

    /**
     * Test: saveFlotilla() properly serializes complex data to JSON
     */
    public function testSaveFlotillaSerializesDataToJson(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri Jun 05');

        // Create complex flotilla with nested structures
        $complexData = [
            'event_id' => 'Fri Jun 05',
            'crewed_boats' => [
                [
                    'boat' => [
                        'key' => 'boat1',
                        'display_name' => 'Boat One',
                        'owner_first_name' => 'Alice',
                        'owner_last_name' => 'Smith',
                        'capacity' => 4,
                    ],
                    'crews' => [
                        ['key' => 'crew1', 'name' => 'Crew One', 'skill' => 2],
                        ['key' => 'crew2', 'name' => 'Crew Two', 'skill' => 1],
                    ],
                ],
                [
                    'boat' => ['key' => 'boat2', 'display_name' => 'Boat Two'],
                    'crews' => [
                        ['key' => 'crew3', 'name' => 'Crew Three', 'skill' => 3],
                    ],
                ],
            ],
            'waitlist_boats' => [
                ['key' => 'boat3', 'display_name' => 'Boat Three'],
            ],
            'waitlist_crews' => [
                ['key' => 'crew4', 'name' => 'Crew Four'],
            ],
        ];

        // Act
        $this->repository->saveFlotilla($eventId, $complexData);

        // Assert
        // Query raw database
        $stmt = $this->pdo->prepare('SELECT flotilla_data FROM flotillas WHERE event_id = ?');
        $stmt->execute(['Fri Jun 05']);
        $jsonString = $stmt->fetchColumn();

        // Verify it's valid JSON
        $this->assertIsString($jsonString);
        $decoded = json_decode($jsonString, true);
        $this->assertNotNull($decoded, 'Should be valid JSON');
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(), 'Should have no JSON errors');

        // Verify structure is preserved
        $this->assertArrayHasKey('event_id', $decoded);
        $this->assertArrayHasKey('crewed_boats', $decoded);
        $this->assertCount(2, $decoded['crewed_boats']);
        $this->assertCount(1, $decoded['waitlist_boats']);
        $this->assertCount(1, $decoded['waitlist_crews']);
        $this->assertCount(2, $decoded['crewed_boats'][0]['crews']);
    }

    /**
     * Test: getFlotilla() returns deserialized array (not JSON string)
     */
    public function testGetFlotillaReturnsDeserializedData(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri May 29');
        $originalData = $this->createTestFlotilla('Fri May 29');

        // Save flotilla
        $this->repository->saveFlotilla($eventId, $originalData);

        // Act
        // Retrieve flotilla
        $retrievedData = $this->repository->getFlotilla($eventId);

        // Assert
        // Verify it's an array, not a JSON string
        $this->assertIsArray($retrievedData);
        $this->assertNotNull($retrievedData);

        // Verify structure matches
        $this->assertEquals('Fri May 29', $retrievedData['event_id']);
        $this->assertArrayHasKey('crewed_boats', $retrievedData);
        $this->assertArrayHasKey('waitlist_boats', $retrievedData);
        $this->assertArrayHasKey('waitlist_crews', $retrievedData);

        // Verify nested data is preserved
        $this->assertCount(1, $retrievedData['crewed_boats']);
        $this->assertEquals('sailaway', $retrievedData['crewed_boats'][0]['boat']['key']);
        $this->assertCount(1, $retrievedData['crewed_boats'][0]['crews']);
    }

    /**
     * Test: getFlotilla() returns null when flotilla doesn't exist
     */
    public function testGetFlotillaReturnsNullWhenNotFound(): void
    {
        // Arrange
        $eventId = EventId::fromString('NonExistent Event');

        // Act
        $result = $this->repository->getFlotilla($eventId);

        // Assert
        $this->assertNull($result, 'Should return null for non-existent flotilla');
    }

    /**
     * Test: deleteFlotilla() removes the record from database
     */
    public function testDeleteFlotillaRemovesRecord(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri May 29');
        $flotillaData = $this->createTestFlotilla('Fri May 29');

        // Save flotilla
        $this->repository->saveFlotilla($eventId, $flotillaData);

        // Verify it exists
        $this->assertTrue($this->repository->flotillaExists($eventId));

        // Act
        // Delete flotilla
        $this->repository->deleteFlotilla($eventId);

        // Assert
        // Verify it's gone
        $this->assertFalse($this->repository->flotillaExists($eventId));
        $this->assertNull($this->repository->getFlotilla($eventId));

        // Verify database record is gone
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM flotillas WHERE event_id = ?');
        $stmt->execute(['Fri May 29']);
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count, 'Database should have no records');
    }

    /**
     * Test: flotillaExists() returns true when flotilla exists
     */
    public function testFlotillaExistsReturnsTrueWhenExists(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri May 29');
        $flotillaData = $this->createTestFlotilla('Fri May 29');

        // Before saving
        $this->assertFalse($this->repository->flotillaExists($eventId));

        // Save flotilla
        $this->repository->saveFlotilla($eventId, $flotillaData);

        // Act & Assert
        // After saving
        $this->assertTrue($this->repository->flotillaExists($eventId));
    }

    /**
     * Test: saveFlotilla() handles empty arrays correctly (not converted to null)
     */
    public function testSaveFlotillaHandlesEmptyData(): void
    {
        // Arrange
        $eventId = EventId::fromString('Fri May 29');

        // Create flotilla with all empty arrays
        $emptyData = [
            'event_id' => 'Fri May 29',
            'crewed_boats' => [],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];

        // Act
        $this->repository->saveFlotilla($eventId, $emptyData);

        // Retrieve and verify
        $retrievedData = $this->repository->getFlotilla($eventId);

        // Assert
        $this->assertNotNull($retrievedData);
        $this->assertIsArray($retrievedData['crewed_boats']);
        $this->assertIsArray($retrievedData['waitlist_boats']);
        $this->assertIsArray($retrievedData['waitlist_crews']);

        // Verify they're empty arrays, not null
        $this->assertCount(0, $retrievedData['crewed_boats']);
        $this->assertCount(0, $retrievedData['waitlist_boats']);
        $this->assertCount(0, $retrievedData['waitlist_crews']);
    }
}
