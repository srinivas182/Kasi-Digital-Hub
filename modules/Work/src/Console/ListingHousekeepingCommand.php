<?php

declare(strict_types=1);

namespace Modules\Work\Console;

use Illuminate\Console\Command;
use Modules\Work\Services\Listings;

final class ListingHousekeepingCommand extends Command
{
    protected $signature = 'kasi:work:listings';

    protected $description = 'Expire listings past their closing date and remind employers 3 days before';

    public function handle(Listings $listings): int
    {
        $result = $listings->housekeeping();
        $this->info("Expired: {$result['expired']}, reminded: {$result['reminded']}");

        return self::SUCCESS;
    }
}
