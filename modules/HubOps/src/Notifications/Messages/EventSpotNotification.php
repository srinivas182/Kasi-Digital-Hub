<?php

declare(strict_types=1);

namespace Modules\HubOps\Notifications\Messages;

use App\Support\Format\SaFormat;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;
use Modules\HubOps\Models\HubEvent;

/** A place opened up and the person moved from the waiting list to the event. */
final class EventSpotNotification extends KasiNotification
{
    public const KEY = 'event_spot';

    public const CATEGORY = 'hub_news';

    public const WHATSAPP_TEMPLATE = 'kasihub_event_spot';

    public function __construct(private readonly HubEvent $event) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.event_spot.title', ['event' => $this->event->title]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.event_spot.body', ['when' => SaFormat::dateTime($this->event->starts_at), 'hub' => $this->event->hub->name]);
    }

    public function url(): string
    {
        return '/events/'.$this->event->id;
    }

    public function important(): bool
    {
        return true;
    }

    public static function whatsappBody(): string
    {
        return 'Good news! A place opened up for {{1}} on {{2}}. You are now booked. Can no longer come? Cancel in the KasiHub app so someone else can go.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->event->title, SaFormat::dateTime($this->event->starts_at)];
    }
}
