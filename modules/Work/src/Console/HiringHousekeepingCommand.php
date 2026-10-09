<?php

declare(strict_types=1);

namespace Modules\Work\Console;

use Illuminate\Console\Command;
use Modules\Work\Services\Hiring;

final class HiringHousekeepingCommand extends Command
{
    protected $signature = 'kasi:work:hiring';

    protected $description = 'No-ghosting outcomes, interview reminders, retention check-ins, applying-open notices, employer reminders, anonymisation';

    public function handle(Hiring $hiring): int
    {
        foreach ($hiring->housekeeping() as $what => $count) {
            $this->line("{$what}: {$count}");
        }

        return self::SUCCESS;
    }
}
