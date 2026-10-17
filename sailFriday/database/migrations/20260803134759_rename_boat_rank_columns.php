<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameBoatRankColumns extends AbstractMigration
{
    public function change(): void
    {
        // Rename boats.rank_flexibility / boats.rank_absence to flexibility_rank /
        // absence_rank for consistency with the crews table's *_rank naming
        $this->table('boats')
            ->renameColumn('rank_flexibility', 'flexibility_rank')
            ->renameColumn('rank_absence', 'absence_rank')
            ->save();
    }
}
