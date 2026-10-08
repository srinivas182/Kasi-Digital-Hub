<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Modules\Core\Notifications\Models\NotificationDelivery;

final class NotificationFailed extends PlatformEvent
{
    public const NAME = 'core.notification.failed';

    public const DESCRIPTION = 'A WhatsApp, SMS or email message could not be delivered.';

    public function __construct(public readonly NotificationDelivery $delivery) {}

    public function userId(): string
    {
        return $this->delivery->user_id;
    }

    public function subject(): array
    {
        return ['notification_delivery', $this->delivery->id];
    }

    public function payload(): array
    {
        return ['notification' => $this->delivery->notification, 'channel' => $this->delivery->channel];
    }
}
