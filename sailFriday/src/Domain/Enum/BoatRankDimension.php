<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Boat Rank Dimensions
 *
 * Defines the dimensions used in multi-dimensional ranking for boats.
 * Rankings are compared lexicographically (left to right) during sorting.
 * Higher rank values = higher priority.
 */
enum BoatRankDimension: int
{
    case FLEXIBILITY = 0;  // 0=flexible (owner is crew), 1=inflexible
    case ABSENCE = 1;      // Count of past no-shows

    /**
     * Get all boat rank dimensions in order
     *
     * @return array<BoatRankDimension>
     */
    public static function all(): array
    {
        return [
            self::FLEXIBILITY,
            self::ABSENCE,
        ];
    }
}
