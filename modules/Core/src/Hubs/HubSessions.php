<?php

declare(strict_types=1);

namespace Modules\Core\Hubs;

use Carbon\CarbonImmutable;
use Modules\Core\Identity\Models\User;

/**
 * Extension point: scheduled sessions at a hub with sign-ups and attendance. Implemented by the hub
 * operations portal (hub events); used by other portals, e.g. KasiLearn cohort sessions, so they
 * reuse event capacity, reminders and QR/staff attendance without depending on that portal.
 */
interface HubSessions
{
    public function schedule(string $hubId, string $title, ?string $description, CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $capacity, ?string $room, User $by): string;

    public function register(string $sessionId, User $person): void;

    public function cancel(string $sessionId, string $reason, User $by): void;

    /** @return list<string> user ids who attended */
    public function attended(string $sessionId): array;
}
