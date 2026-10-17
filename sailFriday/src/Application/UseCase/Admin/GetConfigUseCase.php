<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Application\Port\Repository\SeasonRepositoryInterface;

/**
 * Get Config Use Case
 *
 * Retrieves current season configuration for admin UI.
 */
class GetConfigUseCase
{
    public function __construct(
        private SeasonRepositoryInterface $seasonRepository
    ) {
    }

    /**
     * Execute the use case
     *
     * @return array<string, mixed> Season configuration data
     */
    public function execute(): array
    {
        return $this->seasonRepository->getConfig();
    }
}
