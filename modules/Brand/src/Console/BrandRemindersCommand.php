<?php

declare(strict_types=1);

namespace Modules\Brand\Console;

use Illuminate\Console\Command;
use Modules\Brand\Services\Calendar;
use Modules\Core\Notifications\Notifier;

/** Every 15 minutes (S31): remind owners of planned posts that are due. */
final class BrandRemindersCommand extends Command
{
    protected $signature = 'kasi:brand:reminders';

    protected $description = 'KasiBrand content calendar reminders';

    public function handle(Calendar $calendar, Notifier $notifier): int
    {
        $this->info('Reminders: '.$calendar->sendReminders($notifier));

        return self::SUCCESS;
    }
}
