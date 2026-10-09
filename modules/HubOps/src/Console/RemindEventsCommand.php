<?php

declare(strict_types=1);

namespace Modules\HubOps\Console;

use Illuminate\Console\Command;
use Modules\Core\Notifications\Notifier;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Notifications\Messages\EventReminderNotification;

/**
 * Hourly: remind people about events starting in about a day (quiet hours are respected by the notifier).
 */
final class RemindEventsCommand extends Command
{
    protected $signature = 'kasi:hub-ops:remind-events';

    protected $description = 'Send reminders for hub events starting in the next 20-28 hours';

    public function handle(Notifier $notifier): int
    {
        $sent = 0;
        HubEvent::query()->with('hub')->where('status', 'scheduled')
            ->whereBetween('starts_at', [now()->addHours(20), now()->addHours(28)])
            ->each(function (HubEvent $event) use ($notifier, &$sent): void {
                EventRegistration::query()->with('user')->where('event_id', $event->id)
                    ->where('status', EventRegistration::REGISTERED)->whereNull('reminded_at')
                    ->each(function (EventRegistration $r) use ($notifier, $event, &$sent): void {
                        $notifier->send($r->user, new EventReminderNotification($event));
                        $r->forceFill(['reminded_at' => now()])->save();
                        $sent++;
                    });
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
