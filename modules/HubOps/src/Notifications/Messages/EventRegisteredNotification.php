<?php

declare(strict_types=1);

namespace Modules\HubOps\Notifications\Messages;

use App\Support\Format\SaFormat;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;
use Modules\HubOps\Models\HubEvent;

/** Confirms a sign-up, or that the person is on the waiting list. */
final class EventRegisteredNotification extends KasiNotification
{
    public const KEY = 'event_registered';

    public const CATEGORY = 'hub_news';

    public const WHATSAPP_TEMPLATE = 'kasihub_event_registered';

    public function __construct(private readonly HubEvent $event, private readonly bool $waitlisted) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp'];
    }

    public function title(User $user): string
    {
        return $this->t($user, $this->waitlisted ? 'notify.event_waitlisted.title' : 'notify.event_registered.title', ['event' => $this->event->title]);
    }

    public function body(User $user): string
    {
        return $this->t($user, $this->waitlisted ? 'notify.event_waitlisted.body' : 'notify.event_registered.body', ['when' => SaFormat::dateTime($this->event->starts_at), 'hub' => $this->event->hub->name]);
    }

    public function url(): string
    {
        return '/events/'.$this->event->id;
    }

    public function dedupeKey(): string
    {
        return 'event-registered:'.$this->event->id.($this->waitlisted ? ':wait' : '');
    }

    public static function whatsappBody(): string
    {
        return 'KasiHub: {{1}} - {{2}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user), $this->body($user)];
    }
}
