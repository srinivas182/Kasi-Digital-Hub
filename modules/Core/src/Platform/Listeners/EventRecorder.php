<?php

declare(strict_types=1);

namespace Modules\Core\Platform\Listeners;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Platform\Models\PlatformEventRecord;

/**
 * Writes every platform event to the append-only event log. Runs synchronously after
 * the transaction commits, so the log never misses or invents events.
 */
final class EventRecorder
{
    public function handle(PlatformEvent $event): void
    {
        [$subjectType, $subjectId] = $event->subject() ?? [null, null];

        PlatformEventRecord::query()->create([
            'name' => $event::NAME,
            'actor_id' => $event->actorId(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_id' => $event->userId(),
            'hub_id' => $event->hubId(),
            'payload' => $event->payload() === [] ? null : $event->payload(),
            'occurred_at' => now(),
        ]);
    }
}
