<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameCrewAndAvailabilityRankColumns extends AbstractMigration
{
    public function change(): void
    {
        // Rename crew_availability.status to selection_rank for consistency
        // with the rank-dimension naming used elsewhere (commitment_rank, etc.)
        $this->table('crew_availability')
            ->renameColumn('status', 'selection_rank')
            ->save();

        // Rename crews.rank_membership / crews.rank_absence to membership_rank /
        // absence_rank for consistency with commitment_rank
        $this->table('crews')
            ->renameColumn('rank_membership', 'membership_rank')
            ->renameColumn('rank_absence', 'absence_rank')
            ->save();
    }
}
