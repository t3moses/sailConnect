<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Application\Port\Repository\CrewRepositoryInterface;

/**
 * Get Crew-Boat History Use Case
 *
 * Returns past crew-to-boat assignment counts, for crew/boat pairs that
 * have appeared in at least one past flotilla. Used to chart, per crew
 * member, how many past flotillas they sailed on each boat.
 */
class GetCrewBoatHistoryUseCase
{
    public function __construct(
        private CrewRepositoryInterface $crewRepository,
    ) {
    }

    /**
     * Execute the use case
     *
     * @return array[] Array of ['crew_name' => string, 'boat_name' => string, 'count' => int]
     */
    public function execute(): array
    {
        return $this->crewRepository->getCrewBoatHistorySummary();
    }
}
