<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Application\DTO\Response\BoatResponse;
use App\Application\DTO\Response\CrewResponse;

/**
 * Profile Response DTO
 *
 * Data Transfer Object for complete user profile (user + crew profile + boat profile).
 */
final readonly class ProfileResponse
{
    public function __construct(
        public UserResponse $user,
        public ?CrewResponse $crewProfile = null,
        public ?BoatResponse $boatProfile = null,
    ) {
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'crewProfile' => $this->crewProfile?->toArray(),
            'boatProfile' => $this->boatProfile?->toArray(),
        ];
    }
}
