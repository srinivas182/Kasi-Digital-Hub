<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Events\EventAttended;
use Modules\HubOps\Events\EventCancelled;
use Modules\HubOps\Events\EventScheduled;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Notifications\Messages\EventCancelledNotification;
use Modules\HubOps\Notifications\Messages\EventRegisteredNotification;
use Modules\HubOps\Notifications\Messages\EventSpotNotification;

/**
 * Events, sign-ups, waiting lists and attendance. Capacity is checked under a row lock on the
 * event so two people can never take the last place at the same time.
 */
final readonly class EventBook
{
    public function __construct(
        private Notifier $notifier,
        private AuditLogger $audit,
        private CheckIns $checkIns,
        private ModerationService $moderation,
    ) {}

    /** @param array<string, mixed> $data */
    public function schedule(Hub $hub, array $data, User $by): HubEvent
    {
        $event = HubEvent::query()->create([...$data, 'hub_id' => $hub->id, 'status' => 'scheduled', 'created_by' => $by->id]);
        $this->audit->record('hub_event.scheduled', meta: ['event' => $event->id, 'hub' => $hub->id], actor: $by);
        event(new EventScheduled($event));
        $this->moderate($event, $by);

        return $event;
    }

    /** @param array<string, mixed> $data */
    public function update(HubEvent $event, array $data, User $by): HubEvent
    {
        if ($event->isCancelled()) {
            throw new DomainException('A cancelled event cannot be changed.');
        }

        $event->update($data);
        $this->audit->record('hub_event.updated', meta: ['event' => $event->id, 'changed' => array_keys($event->getChanges())], actor: $by);
        if ($event->wasChanged(['title', 'description', 'audience'])) {
            $this->moderate($event, $by);
        }
        $this->promote($event); // more places may have opened

        return $event;
    }

    /**
     * Sign someone up. When the event is full they join the waiting list.
     */
    public function register(HubEvent $event, User $person, ?User $by = null): EventRegistration
    {
        if ($event->isCancelled() || $event->isPast()) {
            throw new DomainException(__('hubops.events.closed'));
        }

        if (! $event->allows($person)) {
            throw new DomainException(__('hubops.events.adults_only'));
        }

        $registration = DB::transaction(function () use ($event, $person, $by): EventRegistration {
            HubEvent::query()->whereKey($event->id)->lockForUpdate()->first();

            $existing = EventRegistration::query()->where('event_id', $event->id)->where('user_id', $person->id)->first();
            if ($existing !== null && $existing->status !== EventRegistration::CANCELLED) {
                return $existing;
            }

            $status = $this->registeredCount($event) < $event->capacity ? EventRegistration::REGISTERED : EventRegistration::WAITLISTED;
            $attributes = ['status' => $status, 'registered_by' => $by?->id, 'attended_at' => null];

            if ($existing !== null) {
                // Re-joining after cancelling: back of the queue.
                $existing->forceFill([...$attributes, 'created_at' => now()])->save();

                return $existing;
            }

            return EventRegistration::query()->create([...$attributes, 'event_id' => $event->id, 'user_id' => $person->id]);
        });

        if ($registration->wasRecentlyCreated || $registration->wasChanged('status')) {
            $this->audit->record('hub_event.registered', $person, meta: ['event' => $event->id, 'status' => $registration->status], actor: $by);
            $this->notifier->send($person, new EventRegisteredNotification($event, $registration->status === EventRegistration::WAITLISTED));
        }

        return $registration;
    }

    public function cancelRegistration(EventRegistration $registration, ?User $by = null): void
    {
        $wasRegistered = $registration->status === EventRegistration::REGISTERED;
        $registration->forceFill(['status' => EventRegistration::CANCELLED])->save();
        $this->audit->record('hub_event.registration_cancelled', $registration->user, meta: ['event' => $registration->event_id], actor: $by);

        if ($wasRegistered) {
            $this->promote($registration->event);
        }
    }

    /**
     * Move people from the waiting list into free places, first come first served.
     *
     * @return int how many were moved up
     */
    public function promote(HubEvent $event): int
    {
        if ($event->isCancelled() || $event->isPast()) {
            return 0;
        }

        $promoted = DB::transaction(function () use ($event): array {
            HubEvent::query()->whereKey($event->id)->lockForUpdate()->first();
            $free = $event->capacity - $this->registeredCount($event);

            if ($free <= 0) {
                return [];
            }

            $next = EventRegistration::query()->where('event_id', $event->id)->where('status', EventRegistration::WAITLISTED)
                ->orderBy('created_at')->orderBy('id')->limit($free)->get();
            $next->each(fn (EventRegistration $r) => $r->forceFill(['status' => EventRegistration::REGISTERED])->save());

            return $next->all();
        });

        foreach ($promoted as $registration) {
            $this->notifier->send($registration->user, new EventSpotNotification($event));
        }

        return count($promoted);
    }

    public function cancelEvent(HubEvent $event, string $reason, User $by): void
    {
        $event->forceFill(['status' => 'cancelled', 'cancel_reason' => $reason])->save();
        $this->audit->record('hub_event.cancelled', meta: ['event' => $event->id, 'reason' => $reason], actor: $by);
        event(new EventCancelled($event, $by->id));

        EventRegistration::query()->with('user')->where('event_id', $event->id)
            ->whereIn('status', [EventRegistration::REGISTERED, EventRegistration::WAITLISTED])
            ->each(fn (EventRegistration $r) => $this->notifier->send($r->user, new EventCancelledNotification($event)));
    }

    /**
     * Record attendance (event QR or ticked by staff). People who turn up without signing up are
     * added if there is room; the visit also counts for the hub.
     */
    public function attend(HubEvent $event, User $person, ?User $by = null): EventRegistration
    {
        if ($event->isCancelled()) {
            throw new DomainException(__('hubops.events.closed'));
        }

        $registration = EventRegistration::query()->where('event_id', $event->id)->where('user_id', $person->id)->first();

        if ($registration === null || $registration->status === EventRegistration::CANCELLED) {
            if (! $event->allows($person) || $this->registeredCount($event) >= $event->capacity) {
                throw new DomainException(__('hubops.events.full'));
            }
            $registration = $registration ?? new EventRegistration(['event_id' => $event->id, 'user_id' => $person->id]);
            $registration->forceFill(['status' => EventRegistration::REGISTERED, 'registered_by' => $by?->id])->save();
        }

        if ($registration->attended_at === null) {
            $registration->forceFill(['attended_at' => now(), 'status' => EventRegistration::REGISTERED])->save();
            $this->checkIns->record($event->hub, $person, 'event', 'event', $by);
            event(new EventAttended($registration->setRelation('event', $event), $by?->id));
        }

        return $registration;
    }

    /**
     * Event text shown to the public is checked; anything doubtful goes to the admin review queue
     * (the event stays visible - staff are trusted, the check catches mistakes and misuse).
     */
    private function moderate(HubEvent $event, User $by): void
    {
        if ($event->audience === 'public') {
            $this->moderation->check(trim($event->title."\n".$event->description), 'hub_event', $event->id, $by);
        }
    }

    public function registeredCount(HubEvent $event): int
    {
        return EventRegistration::query()->where('event_id', $event->id)->where('status', EventRegistration::REGISTERED)->count();
    }
}
