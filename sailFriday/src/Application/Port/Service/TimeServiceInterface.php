<?php

declare(strict_types=1);

namespace App\Application\Port\Service;

use App\Domain\Enum\TimeSource;

/**
 * Time Service Interface
 *
 * Defines the contract for time operations.
 * Supports both production (real) time and simulated time for testing.
 */
interface TimeServiceInterface
{
    /**
     * Get current date/time
     *
     * @return \DateTimeImmutable
     */
    public function now(): \DateTimeImmutable;

    /**
     * Get current date (without time)
     *
     * @return \DateTimeImmutable
     */
    public function today(): \DateTimeImmutable;

    /**
     * Get time source mode
     *
     * @return TimeSource
     */
    public function getTimeSource(): TimeSource;

    /**
     * Set time source mode
     *
     * @param TimeSource $source
     * @param \DateTimeInterface|null $simulatedDate Required if source is SIMULATED
     * @return void
     */
    public function setTimeSource(TimeSource $source, ?\DateTimeInterface $simulatedDate = null): void;

    /**
     * Check if time is within blackout window
     *
     * @param string $blackoutFrom Time string (e.g., "10:00:00")
     * @param string $blackoutTo Time string (e.g., "18:00:00")
     * @return bool
     */
    public function isInBlackoutWindow(string $blackoutFrom, string $blackoutTo): bool;

    /**
     * Parse time string to DateTime
     *
     * @param string $timeString
     * @return \DateTimeImmutable
     */
    public function parseTime(string $timeString): \DateTimeImmutable;

    /**
     * Format DateTime to string
     *
     * @param \DateTimeInterface $dateTime
     * @param string $format
     * @return string
     */
    public function format(\DateTimeInterface $dateTime, string $format = 'Y-m-d H:i:s'): string;
}
