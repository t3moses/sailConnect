<?php

declare(strict_types=1);

namespace App\Application\Port\Service;

use App\Domain\Entity\User;

/**
 * Email Template Service Interface
 *
 * Provides email template rendering for various notification types.
 * All templates generate HTML emails with consistent styling.
 */
interface EmailTemplateServiceInterface
{
    /**
     * Render crew registration notification email
     *
     * @param User $user User entity
     * @param array $profile Crew profile data containing:
     *   - firstName: string
     *   - lastName: string
     *   - displayName: string|null
     *   - mobile: string|null
     *   - skill: int
     *   - membershipNumber: string|null
     *   - partnerKey: string|null
     *   - socialPreference: mixed
     *   - experience: string|null
     * @return string HTML email body
     */
    public function renderCrewRegistrationNotification(User $user, array $profile): string;

    /**
     * Render boat owner registration notification email
     *
     * @param User $user User entity
     * @param array $profile Boat profile data containing:
     *   - displayName: string|null
     *   - ownerFirstName: string
     *   - ownerLastName: string
     *   - ownerMobile: string|null
     *   - minBerths: int
     *   - maxBerths: int
     *   - assistanceRequired: mixed
     *   - socialPreference: mixed
     * @return string HTML email body
     */
    public function renderBoatOwnerRegistrationNotification(User $user, array $profile): string;

    /**
     * Render crew profile update notification email (sent to admin on profile edit)
     *
     * @param User $user User entity
     * @param array $profile Revised crew profile data (see renderCrewRegistrationNotification for shape)
     * @return string HTML email body
     */
    public function renderCrewProfileUpdateNotification(User $user, array $profile): string;

    /**
     * Render boat owner profile update notification email (sent to admin on profile edit)
     *
     * @param User $user User entity
     * @param array $profile Revised boat profile data (see renderBoatOwnerRegistrationNotification for shape)
     * @return string HTML email body
     */
    public function renderBoatOwnerProfileUpdateNotification(User $user, array $profile): string;

    /**
     * Render crew reminder notification email (sent ~24h before event)
     *
     * @param string $firstName Crew member's first name
     * @param string $eventId Event identifier (e.g. "Fri May 29")
     * @param string $eventDate Event date (YYYY-MM-DD)
     * @param string $startTime Event start time (HH:MM:SS)
     * @return string HTML email body
     */
    public function renderCrewReminderNotification(
        string $firstName,
        string $eventId,
        string $eventDate,
        string $startTime
    ): string;

    /**
     * Render boat owner reminder notification email (sent ~24h before event)
     *
     * @param string $ownerFirstName Boat owner's first name
     * @param string $displayName Boat's display name
     * @param string $eventId Event identifier (e.g. "Fri May 29")
     * @param string $eventDate Event date (YYYY-MM-DD)
     * @param string $startTime Event start time (HH:MM:SS)
     * @return string HTML email body
     */
    public function renderBoatOwnerReminderNotification(
        string $ownerFirstName,
        string $displayName,
        string $eventId,
        string $eventDate,
        string $startTime
    ): string;

    /**
     * Render crew list notification email (sent to admin + boat owners at blackout start)
     *
     * @param string $eventId Event identifier (e.g. "Fri May 29")
     * @param string $eventDate Event date (YYYY-MM-DD)
     * @param array $crewedBoats Array of crewed boat data from flotilla['crewed_boats']
     * @return string HTML email body
     */
    public function renderCrewListNotification(
        string $eventId,
        string $eventDate,
        array $crewedBoats
    ): string;

    /**
     * Render per-owner crew list email (sent individually to each boat owner in
     * the persisted flotilla when the blackout window opens on event day)
     *
     * @param string $ownerFirstName Boat owner's first name
     * @param string $boatDisplayName Display name of the owner's boat
     * @param string[] $crewDisplayNames Display names of the crew assigned to this boat
     * @param string $eventId Event identifier (e.g. "Fri May 29")
     * @param string $eventDate Event date (YYYY-MM-DD)
     * @param string $startTime Event start time (HH:MM:SS)
     * @return string HTML email body
     */
    public function renderBoatOwnerCrewListNotification(
        string $ownerFirstName,
        string $boatDisplayName,
        array $crewDisplayNames,
        string $eventId,
        string $eventDate,
        string $startTime
    ): string;

    /**
     * Render assignment reminder email (sent individually to each assigned crew
     * when the blackout window opens on event day)
     *
     * @param string $firstName Crew member's first name
     * @param string $boatDisplayName Display name of the boat the crew is assigned to
     * @param string $ownerFirstName First name of the boat owner
     * @param string $eventId Event identifier (e.g. "Fri May 29")
     * @param string $eventDate Event date (YYYY-MM-DD)
     * @param string $startTime Event start time (HH:MM:SS)
     * @return string HTML email body
     */
    public function renderAssignmentReminderNotification(
        string $firstName,
        string $boatDisplayName,
        string $ownerFirstName,
        string $eventId,
        string $eventDate,
        string $startTime
    ): string;

    /**
     * Render welcome email for newly registered user
     *
     * @param string $displayName The user's registered display name
     * @return string HTML email body
     */
    public function renderWelcomeNotification(string $displayName): string;

    /**
     * Render password reset email
     *
     * @param string $resetUrl Full URL including the plain token
     * @param \DateTimeImmutable $expiresAt Token expiry time
     * @return string HTML email body
     */
    public function renderPasswordResetNotification(string $resetUrl, \DateTimeImmutable $expiresAt): string;
}
