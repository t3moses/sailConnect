<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add Active To Crews Migration
 *
 * Adds an `active` boolean flag to crews. New crews default to active. A crew
 * is deactivated when a boat owner flags them for a no-show while their
 * commitment rank is already at 0 (see FlagAssignedCrewUseCase). An inactive
 * crew is blocked from updating their own availability
 * (UpdateCrewAvailabilityUseCase).
 */
final class AddActiveToCrews extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('crews');
        $table->addColumn('active', 'boolean', ['default' => 1, 'null' => false])
              ->update();
    }
}
