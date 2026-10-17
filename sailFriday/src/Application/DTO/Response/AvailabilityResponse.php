<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * Availability Response DTO
 *
 * Data transfer object for crew availability information.
 */
final readonly class AvailabilityResponse
{
    /**
     * @param array<string, bool> $availability Map of ISO date => boolean (available or not)
     */
    public function __construct(
        public array $availability
    ) {
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'availability' => $this->availability,
        ];
    }
}
