<?php

declare(strict_types=1);

namespace Modules\Market\Console;

use Illuminate\Console\Command;
use Modules\Market\Services\Ordering;

/** Hourly: remind traders about orders waiting 24 hours; lapse orders waiting 48 hours (no ghosting). */
final class MarketHourlyCommand extends Command
{
    protected $signature = 'kasi:market:hourly';

    protected $description = 'KasiMarket order reminders and lapses';

    public function handle(Ordering $ordering): int
    {
        $done = $ordering->sweep();
        $this->info("Reminded: {$done['reminded']}, lapsed: {$done['lapsed']}");

        return self::SUCCESS;
    }
}
