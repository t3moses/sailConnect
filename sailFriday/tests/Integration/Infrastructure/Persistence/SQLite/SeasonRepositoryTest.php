<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\SQLite;

use App\Infrastructure\Persistence\SQLite\SeasonRepository;
use App\Domain\ValueObject\EventId;
use App\Domain\Enum\TimeSource;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for SeasonRepository
 *
 * Tests database operations for season configuration and flotilla management:
 * - Season config CRUD
 * - Time source management
 * - Flotilla CRUD operations
 * - JSON serialization/deserialization
 */
class SeasonRepositoryTest extends IntegrationTestCase
{
    private SeasonRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SeasonRepository();
    }

    public function testGetConfigReturnsSeasonConfiguration(): void
    {
        $config = $this->repository->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('year', $config);
        $this->assertArrayHasKey('source', $config);
        $this->assertArrayHasKey('start_time', $config);
        $this->assertArrayHasKey('finish_time', $config);
    }

    public function testGetYearReturnsCorrectYear(): void
    {
        $year = $this->repository->getYear();

        $this->assertIsInt($year);
        $this->assertEquals(2026, $year);
    }

    public function testGetTimeSourceReturnsConfiguredSource(): void
    {
        $timeSource = $this->repository->getTimeSource();

        $this->assertInstanceOf(TimeSource::class, $timeSource);
        // Default from setup is simulated
        $this->assertEquals(TimeSource::SIMULATED, $timeSource);
    }

    public function testGetSimulatedDateReturnsDateWhenSimulated(): void
    {
        $simulatedDate = $this->repository->getSimulatedDate();

        $this->assertNotNull($simulatedDate);
        $this->assertInstanceOf(\DateTimeInterface::class, $simulatedDate);
    }

    public function testSetTimeSourceUpdatesConfiguration(): void
    {
        // Change to production
        $this->repository->setTimeSource(TimeSource::PRODUCTION);

        $this->assertEquals(TimeSource::PRODUCTION, $this->repository->getTimeSource());
        $this->assertNull($this->repository->getSimulatedDate());
    }

    public function testSetTimeSourceWithSimulatedDateStoresDate(): void
    {
        $date = new \DateTimeImmutable('2026-07-15');

        $this->repository->setTimeSource(TimeSource::SIMULATED, $date);

        $this->assertEquals(TimeSource::SIMULATED, $this->repository->getTimeSource());
        $this->assertNotNull($this->repository->getSimulatedDate());
        $this->assertEquals('2026-07-15', $this->repository->getSimulatedDate()->format('Y-m-d'));
    }

    public function testUpdateConfigUpdatesAllFields(): void
    {
        $newConfig = [
            'year' => 2027,
            'source' => 'production',
            'simulated_date' => null,
            'start_time' => '13:00:00',
            'finish_time' => '18:00:00',
            'blackout_from' => '11:00:00',
            'blackout_to' => '19:00:00',
        ];

        $this->repository->updateConfig($newConfig);

        $config = $this->repository->getConfig();
        $this->assertEquals(2027, $config['year']);
        $this->assertEquals('production', $config['source']);
        $this->assertEquals('13:00:00', $config['start_time']);
        $this->assertEquals('18:00:00', $config['finish_time']);
    }

    public function testSaveFlotillaCreatesNewFlotilla(): void
    {
        $eventId = EventId::fromString('2026-06-01');
        $this->createTestEvent('2026-06-01', '2026-06-01');
        $flotillaData = [
            'crewed_boats' => [
                [
                    'boat' => [
                        'key' => 'smith-john',
                        'display_name' => 'Serenity',
                    ],
                    'crews' => [
                        [
                            'key' => 'doe-jane',
                            'display_name' => 'Jane Doe',
                        ],
                    ],
                ],
            ],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];

        $this->repository->saveFlotilla($eventId, $flotillaData);

        $this->assertTrue($this->repository->flotillaExists($eventId));
    }

    public function testGetFlotillaReturnsStoredFlotilla(): void
    {
        $eventId = EventId::fromString('2026-06-05');
        $this->createTestEvent('2026-06-05', '2026-06-05');
        $flotillaData = [
            'crewed_boats' => [
                [
                    'boat' => ['key' => 'boat-1', 'display_name' => 'Boat One'],
                    'crews' => [
                        ['key' => 'crew-1', 'display_name' => 'Crew One'],
                        ['key' => 'crew-2', 'display_name' => 'Crew Two'],
                    ],
                ],
            ],
            'waitlist_boats' => [
                ['key' => 'boat-2', 'display_name' => 'Boat Two'],
            ],
            'waitlist_crews' => [
                ['key' => 'crew-3', 'display_name' => 'Crew Three'],
            ],
        ];

        $this->repository->saveFlotilla($eventId, $flotillaData);

        $retrieved = $this->repository->getFlotilla($eventId);

        $this->assertNotNull($retrieved);
        $this->assertIsArray($retrieved);
        $this->assertArrayHasKey('crewed_boats', $retrieved);
        $this->assertArrayHasKey('waitlist_boats', $retrieved);
        $this->assertArrayHasKey('waitlist_crews', $retrieved);
        $this->assertCount(1, $retrieved['crewed_boats']);
        $this->assertCount(1, $retrieved['waitlist_boats']);
        $this->assertCount(1, $retrieved['waitlist_crews']);
    }

    public function testGetFlotillaReturnsNullWhenNotExists(): void
    {
        $eventId = EventId::fromString('2026-12-31');

        $result = $this->repository->getFlotilla($eventId);

        $this->assertNull($result);
    }

    public function testSaveFlotillaUpdatesExistingFlotilla(): void
    {
        $eventId = EventId::fromString('2026-06-10');
        $this->createTestEvent('2026-06-10', '2026-06-10');

        // Save initial flotilla
        $initialData = [
            'crewed_boats' => [
                [
                    'boat' => ['key' => 'boat-1', 'display_name' => 'Original Boat'],
                    'crews' => [],
                ],
            ],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];
        $this->repository->saveFlotilla($eventId, $initialData);

        // Update flotilla
        $updatedData = [
            'crewed_boats' => [
                [
                    'boat' => ['key' => 'boat-2', 'display_name' => 'Updated Boat'],
                    'crews' => [
                        ['key' => 'crew-1', 'display_name' => 'New Crew'],
                    ],
                ],
            ],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];
        $this->repository->saveFlotilla($eventId, $updatedData);

        $retrieved = $this->repository->getFlotilla($eventId);

        $this->assertEquals('boat-2', $retrieved['crewed_boats'][0]['boat']['key']);
        $this->assertEquals('Updated Boat', $retrieved['crewed_boats'][0]['boat']['display_name']);
        $this->assertCount(1, $retrieved['crewed_boats'][0]['crews']);
    }

    public function testDeleteFlotillaRemovesFlotilla(): void
    {
        $eventId = EventId::fromString('2026-06-15');
        $this->createTestEvent('2026-06-15', '2026-06-15');

        // Create flotilla
        $flotillaData = [
            'crewed_boats' => [],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];
        $this->repository->saveFlotilla($eventId, $flotillaData);

        // Verify it exists
        $this->assertTrue($this->repository->flotillaExists($eventId));

        // Delete it
        $this->repository->deleteFlotilla($eventId);

        // Verify it's gone
        $this->assertFalse($this->repository->flotillaExists($eventId));
        $this->assertNull($this->repository->getFlotilla($eventId));
    }

    public function testDeleteFlotillaOnNonExistentFlotillaDoesNotThrowError(): void
    {
        $eventId = EventId::fromString('2026-12-25');

        // Should not throw exception
        $this->repository->deleteFlotilla($eventId);

        $this->assertFalse($this->repository->flotillaExists($eventId));
    }

    public function testFlotillaExistsReturnsTrueForExistingFlotilla(): void
    {
        $eventId = EventId::fromString('2026-06-20');
        $this->createTestEvent('2026-06-20', '2026-06-20');

        $flotillaData = [
            'crewed_boats' => [],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];
        $this->repository->saveFlotilla($eventId, $flotillaData);

        $this->assertTrue($this->repository->flotillaExists($eventId));
    }

    public function testFlotillaExistsReturnsFalseForNonExistentFlotilla(): void
    {
        $eventId = EventId::fromString('2026-12-31');

        $this->assertFalse($this->repository->flotillaExists($eventId));
    }

    public function testFlotillaWithComplexStructureIsPreserved(): void
    {
        $eventId = EventId::fromString('2026-06-25');
        $this->createTestEvent('2026-06-25', '2026-06-25');

        $complexFlotilla = [
            'crewed_boats' => [
                [
                    'boat' => [
                        'key' => 'smith-john',
                        'display_name' => 'Serenity',
                        'owner_first_name' => 'John',
                        'owner_last_name' => 'Smith',
                        'owner_email' => 'john@example.com',
                        'berths' => 6,
                        'requires_assistance' => true,
                    ],
                    'crews' => [
                        [
                            'key' => 'doe-jane',
                            'display_name' => 'Jane Doe',
                            'first_name' => 'Jane',
                            'last_name' => 'Doe',
                            'skill' => 2,
                        ],
                        [
                            'key' => 'brown-bob',
                            'display_name' => 'Bob Brown',
                            'first_name' => 'Bob',
                            'last_name' => 'Brown',
                            'skill' => 1,
                        ],
                    ],
                ],
                [
                    'boat' => [
                        'key' => 'jones-mary',
                        'display_name' => 'Voyager',
                        'owner_first_name' => 'Mary',
                        'owner_last_name' => 'Jones',
                        'owner_email' => 'mary@example.com',
                        'berths' => 4,
                        'requires_assistance' => false,
                    ],
                    'crews' => [
                        [
                            'key' => 'white-alice',
                            'display_name' => 'Alice White',
                            'first_name' => 'Alice',
                            'last_name' => 'White',
                            'skill' => 2,
                        ],
                    ],
                ],
            ],
            'waitlist_boats' => [
                [
                    'key' => 'green-tom',
                    'display_name' => 'Sea Breeze',
                    'owner_first_name' => 'Tom',
                    'owner_last_name' => 'Green',
                ],
            ],
            'waitlist_crews' => [
                [
                    'key' => 'black-sue',
                    'display_name' => 'Sue Black',
                    'first_name' => 'Sue',
                    'last_name' => 'Black',
                    'skill' => 0,
                ],
                [
                    'key' => 'red-paul',
                    'display_name' => 'Paul Red',
                    'first_name' => 'Paul',
                    'last_name' => 'Red',
                    'skill' => 1,
                ],
            ],
        ];

        $this->repository->saveFlotilla($eventId, $complexFlotilla);

        $retrieved = $this->repository->getFlotilla($eventId);

        // Verify structure is preserved
        $this->assertCount(2, $retrieved['crewed_boats']);
        $this->assertCount(2, $retrieved['crewed_boats'][0]['crews']);
        $this->assertCount(1, $retrieved['crewed_boats'][1]['crews']);
        $this->assertCount(1, $retrieved['waitlist_boats']);
        $this->assertCount(2, $retrieved['waitlist_crews']);

        // Verify data integrity
        $this->assertEquals('Serenity', $retrieved['crewed_boats'][0]['boat']['display_name']);
        $this->assertTrue($retrieved['crewed_boats'][0]['boat']['requires_assistance']);
        $this->assertEquals('Jane Doe', $retrieved['crewed_boats'][0]['crews'][0]['display_name']);
        $this->assertEquals('Voyager', $retrieved['crewed_boats'][1]['boat']['display_name']);
        $this->assertEquals('Sea Breeze', $retrieved['waitlist_boats'][0]['display_name']);
        $this->assertEquals('Sue Black', $retrieved['waitlist_crews'][0]['display_name']);
    }

    public function testFlotillaWithEmptyArraysIsPreserved(): void
    {
        $eventId = EventId::fromString('2026-06-30');
        $this->createTestEvent('2026-06-30', '2026-06-30');

        $emptyFlotilla = [
            'crewed_boats' => [],
            'waitlist_boats' => [],
            'waitlist_crews' => [],
        ];

        $this->repository->saveFlotilla($eventId, $emptyFlotilla);

        $retrieved = $this->repository->getFlotilla($eventId);

        $this->assertNotNull($retrieved);
        $this->assertEmpty($retrieved['crewed_boats']);
        $this->assertEmpty($retrieved['waitlist_boats']);
        $this->assertEmpty($retrieved['waitlist_crews']);
    }

    public function testMultipleFlotillasCanExistIndependently(): void
    {
        $event1 = EventId::fromString('2026-07-01');
        $event2 = EventId::fromString('2026-07-15');
        $event3 = EventId::fromString('2026-08-01');

        $this->createTestEvent('2026-07-01', '2026-07-01');
        $this->createTestEvent('2026-07-15', '2026-07-15');
        $this->createTestEvent('2026-08-01', '2026-08-01');

        $flotilla1 = ['crewed_boats' => [['boat' => ['key' => 'boat-1', 'display_name' => 'Flotilla 1'],'crews' => []]], 'waitlist_boats' => [], 'waitlist_crews' => []];
        $flotilla2 = ['crewed_boats' => [['boat' => ['key' => 'boat-2', 'display_name' => 'Flotilla 2'],'crews' => []]], 'waitlist_boats' => [], 'waitlist_crews' => []];
        $flotilla3 = ['crewed_boats' => [['boat' => ['key' => 'boat-3', 'display_name' => 'Flotilla 3'],'crews' => []]], 'waitlist_boats' => [], 'waitlist_crews' => []];

        $this->repository->saveFlotilla($event1, $flotilla1);
        $this->repository->saveFlotilla($event2, $flotilla2);
        $this->repository->saveFlotilla($event3, $flotilla3);

        $retrieved1 = $this->repository->getFlotilla($event1);
        $retrieved2 = $this->repository->getFlotilla($event2);
        $retrieved3 = $this->repository->getFlotilla($event3);

        $this->assertEquals('Flotilla 1', $retrieved1['crewed_boats'][0]['boat']['display_name']);
        $this->assertEquals('Flotilla 2', $retrieved2['crewed_boats'][0]['boat']['display_name']);
        $this->assertEquals('Flotilla 3', $retrieved3['crewed_boats'][0]['boat']['display_name']);
    }

    public function testConfigurationPersistsAcrossRepositoryInstances(): void
    {
        // Update config
        $newConfig = [
            'year' => 2028,
            'source' => 'production',
            'simulated_date' => null,
            'start_time' => '14:00:00',
            'finish_time' => '19:00:00',
            'blackout_from' => '12:00:00',
            'blackout_to' => '20:00:00',
        ];
        $this->repository->updateConfig($newConfig);

        // Create new repository instance
        $newRepository = new SeasonRepository();

        // Verify config persisted
        $config = $newRepository->getConfig();
        $this->assertEquals(2028, $config['year']);
        $this->assertEquals('14:00:00', $config['start_time']);
    }
}
