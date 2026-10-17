<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Assignment Rules
 *
 * Defines the 6 rules used in crew-to-boat assignment optimization.
 * Rules are applied in the order defined here (priority order).
 */
enum AssignmentRule: int
{
    case ASSIST = 0;      // Boats requiring assistance get appropriate crew
    case WHITELIST = 1;   // Crew assigned to boats they've whitelisted
    case HIGH_SKILL = 2;  // Balance high-skill crew distribution
    case LOW_SKILL = 3;   // Balance low-skill crew distribution
    case PARTNER = 4;     // Keep requested partnerships together
    case REPEAT = 5;      // Minimize crew repeating same boat

    /**
     * Get all rules in priority order
     *
     * @return array<AssignmentRule>
     */
    public static function priorityOrder(): array
    {
        return [
            self::ASSIST,
            self::WHITELIST,
            self::HIGH_SKILL,
            self::LOW_SKILL,
            self::PARTNER,
            self::REPEAT,
        ];
    }

    /**
     * Get rule name for display
     */
    public function getName(): string
    {
        return match ($this) {
            self::ASSIST => 'Assistance',
            self::WHITELIST => 'Whitelist',
            self::HIGH_SKILL => 'High Skill',
            self::LOW_SKILL => 'Low Skill',
            self::PARTNER => 'Partner',
            self::REPEAT => 'Repeat',
        };
    }
}
