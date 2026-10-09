<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Services\EventBook;

/**
 * Events for everyone signed in: browse, sign up, cancel, and check in at the event.
 */
final class VisitorEventsController
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $hubId = $request->query('hub') ?: $user->home_hub_id;

        $events = HubEvent::query()->with('hub:id,name')->where('status', 'scheduled')->where('ends_at', '>', now())
            ->whereIn('audience', $user->isMinor() ? ['learners'] : HubEvent::AUDIENCES)
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->orderBy('starts_at')->limit(50)->get();

        $mine = EventRegistration::query()->where('user_id', $user->id)->whereIn('event_id', $events->pluck('id'))
            ->where('status', '!=', EventRegistration::CANCELLED)->pluck('status', 'event_id');

        return Inertia::render('HubOps/Visitor/Events', [
            'hubId' => $hubId,
            'hubs' => Hub::query()->where('status', 'live')->orderBy('name')->get(['id', 'name']),
            'events' => $events->map(fn (HubEvent $e): array => $this->card($e) + ['myStatus' => $mine[$e->id] ?? null])->values(),
        ]);
    }

    public function show(Request $request, HubEvent $event, EventBook $book): Response
    {
        $user = $this->user($request);
        abort_if($event->audience !== 'public' && ! $event->allows($user), 404);
        $mine = EventRegistration::query()->where('event_id', $event->id)->where('user_id', $user->id)->first();

        return Inertia::render('HubOps/Visitor/Event', [
            'event' => $this->card($event->load('hub:id,name,address,slug')) + [
                'description' => $event->description,
                'address' => $event->hub->address,
                'cancelReason' => $event->cancel_reason,
                'placesLeft' => max(0, $event->capacity - $book->registeredCount($event)),
                'allowed' => $event->allows($user),
                'past' => $event->isPast(),
            ],
            'myStatus' => $mine !== null && $mine->status !== EventRegistration::CANCELLED ? $mine->status : null,
            'attended' => $mine?->attended_at !== null,
        ]);
    }

    public function register(Request $request, HubEvent $event, EventBook $book): RedirectResponse
    {
        try {
            $registration = $book->register($event, $this->user($request));
        } catch (DomainException $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }

        return back()->with('status', __($registration->status === EventRegistration::WAITLISTED ? 'hubops.events.you_waitlisted' : 'hubops.events.you_registered'));
    }

    public function cancel(Request $request, HubEvent $event, EventBook $book): RedirectResponse
    {
        $registration = EventRegistration::query()->where('event_id', $event->id)->where('user_id', $this->user($request)->id)
            ->where('status', '!=', EventRegistration::CANCELLED)->firstOrFail();
        $book->cancelRegistration($registration);

        return back()->with('status', __('hubops.events.you_cancelled'));
    }

    /** Opened by scanning the event QR code (a signed link that stops working after the event). */
    public function attend(Request $request, HubEvent $event, EventBook $book): RedirectResponse
    {
        if (! $event->attendanceOpen()) {
            return to_route('events.show', $event)->withErrors(['event' => __('hubops.events.attendance_closed')]);
        }

        try {
            $book->attend($event, $this->user($request));
        } catch (DomainException $e) {
            return to_route('events.show', $event)->withErrors(['event' => $e->getMessage()]);
        }

        return to_route('events.show', $event)->with('status', __('hubops.events.you_attended'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array<string, mixed> */
    private function card(HubEvent $e): array
    {
        return [
            'id' => $e->id, 'type' => $e->type, 'title' => $e->title, 'room' => $e->room, 'hub' => $e->hub->name,
            'startsAt' => $e->starts_at->toIso8601String(), 'endsAt' => $e->ends_at->toIso8601String(),
            'audience' => $e->audience, 'status' => $e->status,
        ];
    }
}
