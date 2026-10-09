<?php

declare(strict_types=1);

namespace Modules\HubOps\Notifications\Messages;

use App\Support\Format\SaFormat;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;
use Modules\HubOps\Models\HubEvent;

/** Sent the day before an event. */
final class EventReminderNotification extends KasiNotification
{
    public const KEY = 'event_reminder';

    public const CATEGORY = 'hub_news';

    public const WHATSAPP_TEMPLATE = 'kasihub_event_reminder';

    public function __construct(private readonly HubEvent $event) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.event_reminder.title', ['event' => $this->event->title]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.event_reminder.body', ['when' => SaFormat::dateTime($this->event->starts_at), 'hub' => $this->event->hub->name, 'room' => $this->event->room ?? '-']);
    }

    public function url(): string
    {
        return '/events/'.$this->event->id;
    }

    public function dedupeKey(): string
    {
        return 'event-reminder:'.$this->event->id;
    }

    public static function whatsappBody(): string
    {
        return 'Reminder: {{1}} is tomorrow, {{2}} at {{3}}. See you there!';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->event->title, SaFormat::dateTime($this->event->starts_at), $this->event->hub->name];
    }
}
