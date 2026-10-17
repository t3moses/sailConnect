<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Skill Level
 *
 * Defines the sailing skill levels for crew members.
 */
enum SkillLevel: int
{
    case NOVICE = 0;        // Low skill
    case INTERMEDIATE = 1;  // Medium skill
    case ADVANCED = 2;      // High skill

    /**
     * Check if this is a high skill level
     */
    public function isHigh(): bool
    {
        return $this === self::ADVANCED;
    }

    /**
     * Check if this is a low skill level
     */
    public function isLow(): bool
    {
        return $this === self::NOVICE;
    }

    /**
     * Get skill level from integer (for legacy compatibility)
     */
    public static function fromInt(int $skill): self
    {
        return match ($skill) {
            0 => self::NOVICE,
            1 => self::INTERMEDIATE,
            2 => self::ADVANCED,
            default => throw new \InvalidArgumentException("Invalid skill level: $skill"),
        };
    }
}
