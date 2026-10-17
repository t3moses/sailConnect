<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Time Source
 *
 * Defines whether the system uses real time or simulated time for testing.
 */
enum TimeSource: string
{
    case PRODUCTION = 'production';  // Use real system time
    case SIMULATED = 'simulated';    // Use configured simulated date/time

    /**
     * Check if using simulated time
     */
    public function isSimulated(): bool
    {
        return $this === self::SIMULATED;
    }

    /**
     * Check if using production time
     */
    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }
}
