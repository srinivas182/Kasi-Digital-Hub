<?php

declare(strict_types=1);

namespace Modules\HubOps\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\HubOps\Models\EventRegistration;

final class EventAttended extends PlatformEvent
{
    public const NAME = 'hubops.event.attended';

    public const DESCRIPTION = 'A person attended a hub event (event QR code or ticked by staff).';

    public function __construct(public readonly EventRegistration $registration, public readonly ?string $by) {}

    public function userId(): string
    {
        return $this->registration->user_id;
    }

    public function actorId(): ?string
    {
        return $this->by;
    }

    public function hubId(): string
    {
        return $this->registration->event->hub_id;
    }

    public function payload(): array
    {
        return ['event' => $this->registration->event_id, 'type' => $this->registration->event->type];
    }
}
