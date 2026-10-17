<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add Initial Commitment Rank To Crews Migration
 *
 * Stores each crew's starting commitment rank (currently always 2, the
 * registration default) as an immutable baseline. Current commitment_rank is
 * now derived as initial_commitment_rank minus the crew's no_shows count,
 * floored at 0 (see RecordNoShowUseCase), rather than being decremented
 * step-by-step.
 */
final class AddInitialCommitmentRankToCrews extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('crews');
        $table->addColumn('initial_commitment_rank', 'integer', ['default' => 2, 'null' => false])
              ->update();
    }
}
