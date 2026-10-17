<?php

declare(strict_types=1);

namespace App\Application\Port\Repository;

use App\Domain\ValueObject\EventId;
use App\Domain\Enum\TimeSource;

/**
 * Season Repository Interface
 *
 * Defines the contract for season configuration and flotilla persistence.
 * Implementations handle database operations for season-wide data.
 */
interface SeasonRepositoryInterface
{
    /**
     * Get season configuration
     *
     * @return array<string, mixed> Configuration data
     */
    public function getConfig(): array;

    /**
     * Update season configuration
     *
     * @param array<string, mixed> $config
     * @return void
     */
    public function updateConfig(array $config): void;

    /**
     * Get current year
     *
     * @return int
     */
    public function getYear(): int;

    /**
     * Get time source (production or simulated)
     *
     * @return TimeSource
     */
    public function getTimeSource(): TimeSource;

    /**
     * Get simulated date (when using simulated time source)
     *
     * @return \DateTimeInterface|null
     */
    public function getSimulatedDate(): ?\DateTimeInterface;

    /**
     * Set time source
     *
     * @param TimeSource $source
     * @param \DateTimeInterface|null $simulatedDate
     * @return void
     */
    public function setTimeSource(TimeSource $source, ?\DateTimeInterface $simulatedDate = null): void;

    /**
     * Save flotilla data for an event
     *
     * @param EventId $eventId
     * @param array<string, mixed> $flotillaData
     * @return void
     */
    public function saveFlotilla(EventId $eventId, array $flotillaData): void;

    /**
     * Get flotilla data for an event
     *
     * @param EventId $eventId
     * @return array<string, mixed>|null Flotilla data or null if not found
     */
    public function getFlotilla(EventId $eventId): ?array;

    /**
     * Delete flotilla data for an event
     *
     * @param EventId $eventId
     * @return void
     */
    public function deleteFlotilla(EventId $eventId): void;

    /**
     * Check if flotilla exists for an event
     *
     * @param EventId $eventId
     * @return bool
     */
    public function flotillaExists(EventId $eventId): bool;
}
