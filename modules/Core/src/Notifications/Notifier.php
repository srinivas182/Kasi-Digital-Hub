<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Carbon\CarbonImmutable;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Notifications\Jobs\DeliverNotification;
use Modules\Core\Notifications\Models\NotificationDelivery;
use Modules\Core\Platform\Models\Update;

/**
 * Decides where a notification goes and queues each delivery:
 *
 * - in-app: always, into the "what changed and why" feed;
 * - WhatsApp: only with the person's WhatsApp consent and preference;
 * - SMS: when WhatsApp is not possible, for security, or as a fallback for important messages;
 * - email: only to a confirmed address.
 *
 * Applies duplicate suppression, a daily message cap, marketing consent and quiet hours
 * (WhatsApp/SMS held until morning), except for security messages.
 */
final readonly class Notifier
{
    public function __construct(
        private NotificationPreferences $preferences,
        private ConsentService $consents,
    ) {}

    /**
     * @return list<NotificationDelivery>
     */
    public function send(User $user, KasiNotification $notification, ?string $eventId = null): array
    {
        $channels = $notification->channels();
        $category = $notification::CATEGORY;
        $security = $category === 'security';
        $deliveries = [];

        if (in_array('in_app', $channels, true)) {
            Update::query()->create([
                'user_id' => $user->id,
                'module' => $notification::MODULE,
                'category' => $category,
                'title' => $notification->title($user),
                'body' => $notification->body($user),
                'cause' => $notification->cause($user),
                'url' => $notification->url(),
                'event_id' => $eventId,
                'created_at' => now(),
            ]);
        }

        if ($category === 'marketing' && $this->consents->state($user)['marketing'] !== true) {
            return [];
        }

        $whatsapp = in_array('whatsapp', $channels, true) && $notification::WHATSAPP_TEMPLATE !== null
            && $user->whatsapp_opt_in && $this->preferences->enabled($user, $category, 'whatsapp');

        if ($whatsapp) {
            $deliveries[] = $this->deliver($user, $notification, 'whatsapp');
        }

        $smsAllowed = $this->preferences->enabled($user, $category, 'sms') || $security;
        $smsWanted = $security || (in_array('sms', $channels, true) && ! $whatsapp);

        if ($smsAllowed && $smsWanted) {
            $deliveries[] = $this->deliver($user, $notification, 'sms');
        }

        if (in_array('email', $channels, true) && $user->email !== null && $user->email_verified_at !== null
            && $this->preferences->enabled($user, $category, 'email')) {
            $deliveries[] = $this->deliver($user, $notification, 'email');
        }

        return $deliveries;
    }

    private function deliver(User $user, KasiNotification $notification, string $channel): NotificationDelivery
    {
        $security = $notification::CATEGORY === 'security';
        $dedupe = $notification->dedupeKey();

        $delivery = new NotificationDelivery([
            'user_id' => $user->id,
            'notification' => $notification::KEY,
            'category' => $notification::CATEGORY,
            'channel' => $channel,
            'status' => NotificationDelivery::QUEUED,
            'dedupe_key' => $dedupe,
            'payload' => [
                'title' => $notification->title($user),
                'body' => $notification->body($user),
                'url' => $notification->url(),
                'important' => $notification->important(),
                'to' => $channel === 'sms' ? $notification->smsTo($user) : null,
                'template' => $notification::WHATSAPP_TEMPLATE,
                'params' => $notification->whatsappParams($user),
            ],
        ]);

        if ($dedupe !== null && NotificationDelivery::query()
            ->where('user_id', $user->id)->where('channel', $channel)->where('dedupe_key', $dedupe)
            ->where('created_at', '>=', now()->subHours((int) config('kasi.notifications.dedupe_hours')))
            ->exists()) {
            return $this->skip($delivery, 'duplicate');
        }

        if (! $security && in_array($channel, ['whatsapp', 'sms'], true)) {
            $today = NotificationDelivery::query()
                ->where('user_id', $user->id)->whereIn('channel', ['whatsapp', 'sms'])
                ->whereIn('status', [NotificationDelivery::QUEUED, NotificationDelivery::HELD, NotificationDelivery::SENT])
                ->where('category', '!=', 'security')
                ->where('created_at', '>=', CarbonImmutable::now('Africa/Johannesburg')->startOfDay()->utc())
                ->count();

            if ($today >= (int) config('kasi.notifications.max_messages_per_day')) {
                return $this->skip($delivery, 'daily_limit');
            }

            if (($release = self::quietHoursEnd()) !== null) {
                $delivery->fill(['status' => NotificationDelivery::HELD, 'scheduled_for' => $release])->save();

                return $delivery;
            }
        }

        $delivery->save();
        DeliverNotification::dispatch($delivery->id)->onQueue($security ? 'security' : 'notifications');

        return $delivery;
    }

    /** When quiet hours (SAST) are in effect, the time they end; otherwise null. */
    public static function quietHoursEnd(?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $local = ($now ?? CarbonImmutable::now())->setTimezone('Africa/Johannesburg');
        $start = (int) config('kasi.notifications.quiet_hours.start');
        $end = (int) config('kasi.notifications.quiet_hours.end');

        if ($local->hour >= $start) {
            return $local->addDay()->setTime($end, 0)->utc();
        }

        if ($local->hour < $end) {
            return $local->setTime($end, 0)->utc();
        }

        return null;
    }

    private function skip(NotificationDelivery $delivery, string $reason): NotificationDelivery
    {
        $delivery->fill(['status' => NotificationDelivery::SKIPPED, 'skip_reason' => $reason])->save();

        return $delivery;
    }
}
