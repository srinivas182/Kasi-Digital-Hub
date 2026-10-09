<?php

declare(strict_types=1);

namespace Modules\HubOps\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\HubOps\Models\HubVisit;

final class VisitRecorded extends PlatformEvent
{
    public const NAME = 'hubops.visit.recorded';

    public const DESCRIPTION = 'Someone visited a hub (QR at the door, front desk, walk-in, event or assisted registration).';

    public function __construct(public readonly HubVisit $visit) {}

    public function userId(): ?string
    {
        return $this->visit->user_id;
    }

    public function actorId(): ?string
    {
        return $this->visit->checked_in_by;
    }

    public function hubId(): string
    {
        return $this->visit->hub_id;
    }

    public function payload(): array
    {
        return ['purpose' => $this->visit->purpose, 'method' => $this->visit->method];
    }
}
