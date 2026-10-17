<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Application\Port\Repository\BoatRepositoryInterface;

/**
 * Get All Boats Use Case
 *
 * Returns a summary list of all boats (for whitelist picker dropdowns).
 */
class GetAllBoatsUseCase
{
    public function __construct(
        private BoatRepositoryInterface $boatRepository,
    ) {
    }

    /**
     * Execute the use case
     *
     * @return array[] Array of boat summaries
     */
    public function execute(): array
    {
        $boats = $this->boatRepository->findAll();

        return array_map(fn($boat) => [
            'key'          => $boat->getKey()->toString(),
            'display_name' => $boat->getDisplayName(),
        ], $boats);
    }
}
