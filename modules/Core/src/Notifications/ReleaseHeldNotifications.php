<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Console\Command;
use Modules\Core\Notifications\Jobs\DeliverNotification;
use Modules\Core\Notifications\Models\NotificationDelivery;

/**
 * Sends WhatsApp/SMS messages that were held during quiet hours (runs every 5 minutes).
 */
final class ReleaseHeldNotifications extends Command
{
    protected $signature = 'kasi:notifications:release';

    protected $description = 'Send messages held during quiet hours';

    public function handle(): int
    {
        $released = 0;

        NotificationDelivery::query()
            ->where('status', NotificationDelivery::HELD)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit(5000)
            ->get()
            ->each(function (NotificationDelivery $delivery) use (&$released): void {
                $delivery->update(['status' => NotificationDelivery::QUEUED]);
                DeliverNotification::dispatch($delivery->id)->onQueue('notifications');
                $released++;
            });

        $this->components->info("Released {$released} held messages.");

        return self::SUCCESS;
    }
}
