<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Application\DTO\Util\KeyTransformer;

/**
 * Flotilla Response DTO
 *
 * Data transfer object for flotilla assignment information.
 */
final readonly class FlotillaResponse
{
    /**
     * @param string $eventId
     * @param array<array<string, mixed>> $crewedBoats
     * @param array<array<string, mixed>> $waitlistBoats
     * @param array<array<string, mixed>> $waitlistCrews
     */
    public function __construct(
        public string $eventId,
        public array $crewedBoats,
        public array $waitlistBoats,
        public array $waitlistCrews,
    ) {
    }

    /**
     * Create from flotilla data
     *
     * @param string $eventId
     * @param array<string, mixed> $flotillaData
     */
    public static function fromFlotilla(string $eventId, array $flotillaData): self
    {
        return new self(
            eventId: $eventId,
            crewedBoats: $flotillaData['crewed_boats'] ?? [],
            waitlistBoats: $flotillaData['waitlist_boats'] ?? [],
            waitlistCrews: $flotillaData['waitlist_crews'] ?? [],
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'crewedBoats' => array_map(
                fn($boat) => KeyTransformer::toCamelCase($boat),
                $this->crewedBoats
            ),
            'waitlistBoats' => array_map(
                fn($boat) => KeyTransformer::toCamelCase($boat),
                $this->waitlistBoats
            ),
            'waitlistCrews' => array_map(
                fn($crew) => KeyTransformer::toCamelCase($crew),
                $this->waitlistCrews
            ),
        ];
    }

    /**
     * Get count of crewed boats
     */
    public function getCrewedBoatCount(): int
    {
        return count($this->crewedBoats);
    }

    /**
     * Get total assigned crew count
     */
    public function getAssignedCrewCount(): int
    {
        $count = 0;
        foreach ($this->crewedBoats as $crewedBoat) {
            $count += count($crewedBoat['crews'] ?? []);
        }
        return $count;
    }
}
