<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Modules\Core\Home\HubActivityProvider;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;

/**
 * Feeds hub events to the public hub page (public events) and the hub home (my events).
 */
final class EventActivity implements HubActivityProvider
{
    public function publicItems(Hub $hub): array
    {
        return array_values(HubEvent::query()->where('hub_id', $hub->id)->where('audience', 'public')->where('status', 'scheduled')
            ->where('ends_at', '>', now())->orderBy('starts_at')->limit(6)->get()
            ->map(static fn (HubEvent $e): array => self::item($e))->all());
    }

    public function personalItems(User $user): array
    {
        return array_values(EventRegistration::query()->with('event.hub:id,name')->where('user_id', $user->id)
            ->whereIn('status', [EventRegistration::REGISTERED, EventRegistration::WAITLISTED])
            ->whereHas('event', fn ($q) => $q->where('status', 'scheduled')->where('ends_at', '>', now()))
            ->get()
            ->map(static fn (EventRegistration $r): array => [...self::item($r->event), 'hub' => $r->event->hub->name, 'status' => $r->status])
            ->all());
    }

    /** @return array{id: string, title: string, type: string, startsAt: string, endsAt: string, room: string|null, href: string} */
    private static function item(HubEvent $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'type' => $e->type, 'startsAt' => $e->starts_at->toIso8601String(),
            'endsAt' => $e->ends_at->toIso8601String(), 'room' => $e->room, 'href' => '/events/'.$e->id,
        ];
    }
}
