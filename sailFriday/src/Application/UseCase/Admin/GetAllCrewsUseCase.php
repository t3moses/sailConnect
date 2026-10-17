<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Application\Port\Repository\CrewRepositoryInterface;

/**
 * Get All Crews Use Case
 *
 * Returns a summary list of all crew members (for partner picker dropdowns).
 */
class GetAllCrewsUseCase
{
    public function __construct(
        private CrewRepositoryInterface $crewRepository,
    ) {
    }

    /**
     * Execute the use case
     *
     * @return array[] Array of crew summaries
     */
    public function execute(): array
    {
        $crews = $this->crewRepository->findAll();

        return array_map(fn($crew) => [
            'key'        => $crew->getKey()->toString(),
            'first_name' => $crew->getFirstName(),
            'last_name'  => $crew->getLastName(),
        ], $crews);
    }
}
