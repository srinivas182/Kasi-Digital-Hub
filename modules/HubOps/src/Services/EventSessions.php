<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Hubs\HubSessions;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;

/** Hub sessions for other portals, built on hub events ("class" type, for learners, not public). */
final readonly class EventSessions implements HubSessions
{
    public function __construct(private EventBook $book) {}

    public function schedule(string $hubId, string $title, ?string $description, CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $capacity, ?string $room, User $by): string
    {
        $event = $this->book->schedule(Hub::query()->findOrFail($hubId), [
            'type' => 'class', 'title' => $title, 'description' => $description, 'room' => $room, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
            'capacity' => $capacity, 'audience' => 'learners',
        ], $by);

        return $event->id;
    }

    public function register(string $sessionId, User $person): void
    {
        $event = HubEvent::query()->findOrFail($sessionId);
        if (! EventRegistration::query()->where('event_id', $event->id)->where('user_id', $person->id)->whereIn('status', ['registered', 'waitlisted', 'attended'])->exists()) {
            $this->book->register($event, $person);
        }
    }

    public function cancel(string $sessionId, string $reason, User $by): void
    {
        $this->book->cancelEvent(HubEvent::query()->findOrFail($sessionId), $reason, $by);
    }

    public function attended(string $sessionId): array
    {
        return array_values(array_map('strval', EventRegistration::query()->where('event_id', $sessionId)->whereNotNull('attended_at')->pluck('user_id')->all()));
    }
}
