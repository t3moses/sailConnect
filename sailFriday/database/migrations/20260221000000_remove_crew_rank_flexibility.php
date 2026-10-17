<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class RemoveCrewRankFlexibility extends AbstractMigration
{
    public function change(): void
    {
        $this->table('crews')
            ->removeColumn('rank_flexibility')
            ->save();
    }
}
