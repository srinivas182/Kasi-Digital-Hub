<?php

declare(strict_types=1);

namespace Modules\Funder\Console;

use Illuminate\Console\Command;
use Modules\Funder\Services\Programmes;

/** On the 5th: freeze last month's programme figures so reports never change after they're sent. */
final class FreezeSnapshotsCommand extends Command
{
    protected $signature = 'kasi:funder:freeze {period? : YYYY-MM, default last month}';

    protected $description = 'Freeze monthly programme snapshots';

    public function handle(Programmes $programmes): int
    {
        $period = (string) ($this->argument('period') ?? now('Africa/Johannesburg')->subMonth()->format('Y-m'));
        $this->info("Programmes frozen for {$period}: ".$programmes->freeze($period));

        return self::SUCCESS;
    }
}
