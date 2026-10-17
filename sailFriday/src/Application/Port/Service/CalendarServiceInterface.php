<?php

declare(strict_types=1);

namespace App\Application\Port\Service;

use App\Domain\ValueObject\EventId;

/**
 * Calendar Service Interface
 *
 * Defines the contract for generating iCalendar (.ics) files.
 * Implementations handle calendar file generation for events.
 */
interface CalendarServiceInterface
{
    /**
     * Generate calendar file for a single event
     *
     * @param EventId $eventId
     * @param \DateTimeInterface $date
     * @param string $startTime
     * @param string $finishTime
     * @param string $location
     * @param string $description
     * @return string iCalendar file content
     */
    public function generateEventCalendar(
        EventId $eventId,
        \DateTimeInterface $date,
        string $startTime,
        string $finishTime,
        string $location,
        string $description
    ): string;

    /**
     * Generate calendar file for entire season
     *
     * @param array<array<string, mixed>> $events Array of event data
     * @return string iCalendar file content
     */
    public function generateSeasonCalendar(array $events): string;

    /**
     * Generate calendar file for crew member's assignments
     *
     * @param string $crewName
     * @param array<array<string, mixed>> $assignments
     * @return string iCalendar file content
     */
    public function generateCrewCalendar(string $crewName, array $assignments): string;

    /**
     * Save calendar file to disk
     *
     * @param string $content iCalendar content
     * @param string $filename
     * @return string Full path to saved file
     */
    public function saveCalendarFile(string $content, string $filename): string;
}
