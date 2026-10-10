<?php

declare(strict_types=1);

namespace Modules\Partner\Console;

use Illuminate\Console\Command;
use Modules\Partner\Services\Referrals;

final class PartnerDailyCommand extends Command
{
    protected $signature = 'kasi:partner:daily';

    protected $description = 'Referral reminders (7 and 14 days), "no response" at 30 days, follow-ups';

    public function handle(Referrals $referrals): int
    {
        foreach ($referrals->housekeeping() as $what => $count) {
            $this->line("{$what}: {$count}");
        }

        return self::SUCCESS;
    }
}
