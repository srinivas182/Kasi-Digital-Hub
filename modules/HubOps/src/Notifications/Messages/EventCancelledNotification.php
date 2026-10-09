<?php

declare(strict_types=1);

namespace Modules\HubOps\Notifications\Messages;

use App\Support\Format\SaFormat;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;
use Modules\HubOps\Models\HubEvent;

/** The hub cancelled an event the person signed up for. */
final class EventCancelledNotification extends KasiNotification
{
    public const KEY = 'event_cancelled';

    public const CATEGORY = 'hub_news';

    public const WHATSAPP_TEMPLATE = 'kasihub_event_cancelled';

    public function __construct(private readonly HubEvent $event) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.event_cancelled.title', ['event' => $this->event->title]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.event_cancelled.body', ['when' => SaFormat::dateTime($this->event->starts_at), 'reason' => (string) $this->event->cancel_reason]);
    }

    public function important(): bool
    {
        return true;
    }

    public static function whatsappBody(): string
    {
        return 'Sorry - {{1}} on {{2}} has been cancelled. Reason: {{3}}.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->event->title, SaFormat::dateTime($this->event->starts_at), (string) $this->event->cancel_reason];
    }
}
