<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Services\EventBook;
use Modules\HubOps\Services\Qr;

/**
 * Hub staff: schedule events, manage sign-ups and waiting lists, take attendance.
 */
final class EventsController extends StaffController
{
    public function index(Request $request): Response
    {
        $hub = $this->hub($request);
        $past = $request->query('when') === 'past';

        $events = HubEvent::query()->where('hub_id', $hub->id)
            ->withCount([
                'registrations as registered_count' => fn ($q) => $q->where('status', EventRegistration::REGISTERED),
                'registrations as waitlisted_count' => fn ($q) => $q->where('status', EventRegistration::WAITLISTED),
                'registrations as attended_count' => fn ($q) => $q->whereNotNull('attended_at'),
            ])
            ->when($past, fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'), fn ($q) => $q->where('ends_at', '>=', now())->orderBy('starts_at'))
            ->paginate(20)->withQueryString();

        return Inertia::render('HubOps/Events/Index', [
            'hubs' => $this->hubProps($request, $hub),
            'when' => $past ? 'past' : 'upcoming',
            'events' => $events->through(fn (HubEvent $e): array => $this->row($e)),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, HubEvent $event): Response
    {
        $this->guardHub($request, $event->hub_id);

        return $this->form($request, $event);
    }

    public function store(Request $request, EventBook $book): RedirectResponse
    {
        $hub = $this->hub($request);
        $event = $book->schedule($hub, $this->validated($request), $this->actor($request));

        return to_route('hubops.events.show', $event)->with('status', __('hubops.events.scheduled'));
    }

    public function update(Request $request, HubEvent $event, EventBook $book): RedirectResponse
    {
        $this->guardHub($request, $event->hub_id);

        try {
            $book->update($event, $this->validated($request, $event), $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['title' => $e->getMessage()]);
        }

        return to_route('hubops.events.show', $event)->with('status', __('hubops.saved'));
    }

    public function show(Request $request, HubEvent $event): Response
    {
        $this->guardHub($request, $event->hub_id);
        $attendUrl = URL::temporarySignedRoute('events.attend', $event->ends_at->addHour(), ['event' => $event->id]);

        return Inertia::render('HubOps/Events/Show', [
            'event' => $this->row($event->loadCount([
                'registrations as registered_count' => fn ($q) => $q->where('status', EventRegistration::REGISTERED),
                'registrations as waitlisted_count' => fn ($q) => $q->where('status', EventRegistration::WAITLISTED),
                'registrations as attended_count' => fn ($q) => $q->whereNotNull('attended_at'),
            ])) + ['description' => $event->description, 'cancelReason' => $event->cancel_reason, 'attendanceOpen' => $event->attendanceOpen()],
            'registrations' => EventRegistration::query()->with('user:id,first_name,last_name,phone')->where('event_id', $event->id)
                ->whereIn('status', [EventRegistration::REGISTERED, EventRegistration::WAITLISTED])
                ->orderByRaw("case when status = 'registered' then 0 else 1 end")->orderBy('created_at')->get()
                ->map(static fn (EventRegistration $r): array => [
                    'id' => $r->id, 'name' => $r->user->fullName(), 'phone' => SaFormat::maskedPhone($r->user->phone),
                    'status' => $r->status, 'attended' => $r->attended_at !== null, 'byStaff' => $r->registered_by !== null,
                ]),
            'attendQr' => $event->isCancelled() || $event->isPast() ? null : Qr::svg($attendUrl, 280),
            'canCancel' => $this->can($request, 'hubops.events.cancel'),
        ]);
    }

    public function cancel(Request $request, HubEvent $event, EventBook $book): RedirectResponse
    {
        $this->guardHub($request, $event->hub_id);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        abort_if($event->isCancelled(), 409);

        $book->cancelEvent($event, $validated['reason'], $this->actor($request));

        return back()->with('status', __('hubops.events.cancelled'));
    }

    /** Sign someone up at the desk, by phone number. */
    public function register(Request $request, HubEvent $event, EventBook $book): RedirectResponse
    {
        $this->guardHub($request, $event->hub_id);
        $validated = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = SaFormat::normalisePhone($validated['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;

        if ($person === null) {
            return back()->withErrors(['phone' => __('hubops.events.no_account')]);
        }

        try {
            $registration = $book->register($event, $person, $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', __($registration->status === EventRegistration::WAITLISTED ? 'hubops.events.added_waitlist' : 'hubops.events.added'));
    }

    public function cancelRegistration(Request $request, HubEvent $event, EventRegistration $registration, EventBook $book): RedirectResponse
    {
        $this->guardHub($request, $event->hub_id);
        abort_unless($registration->event_id === $event->id, 404);

        $book->cancelRegistration($registration, $this->actor($request));

        return back()->with('status', __('hubops.events.removed'));
    }

    public function attend(Request $request, HubEvent $event, EventRegistration $registration, EventBook $book): RedirectResponse
    {
        $this->guardHub($request, $event->hub_id);
        abort_unless($registration->event_id === $event->id, 404);

        try {
            $book->attend($event, $registration->user, $this->actor($request));
        } catch (DomainException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        return back()->with('status', __('hubops.events.attended'));
    }

    private function form(Request $request, ?HubEvent $event): Response
    {
        $hub = $this->hub($request);

        return Inertia::render('HubOps/Events/Edit', [
            'hubs' => $this->hubProps($request, $hub),
            'event' => $event === null ? null : [
                'id' => $event->id, 'type' => $event->type, 'title' => $event->title, 'description' => $event->description, 'room' => $event->room,
                'startsAt' => $event->starts_at->setTimezone('Africa/Johannesburg')->format('Y-m-d\TH:i'),
                'endsAt' => $event->ends_at->setTimezone('Africa/Johannesburg')->format('Y-m-d\TH:i'),
                'capacity' => $event->capacity, 'audience' => $event->audience,
            ],
            'types' => HubEvent::TYPES,
            'audiences' => HubEvent::AUDIENCES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?HubEvent $event = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(HubEvent::TYPES)],
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'room' => ['nullable', 'string', 'max:80'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', $event === null ? 'after:now' : 'date'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:2000'],
            'audience' => ['required', Rule::in(HubEvent::AUDIENCES)],
        ]);

        // Times are entered in South African time and stored in UTC.
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data[$field], 'Africa/Johannesburg')?->utc();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function row(HubEvent $e): array
    {
        return [
            'id' => $e->id, 'type' => $e->type, 'title' => $e->title, 'room' => $e->room,
            'startsAt' => $e->starts_at->toIso8601String(), 'endsAt' => $e->ends_at->toIso8601String(),
            'capacity' => $e->capacity, 'audience' => $e->audience, 'status' => $e->status,
            'registered' => (int) ($e->registered_count ?? 0), 'waitlisted' => (int) ($e->waitlisted_count ?? 0), 'attended' => (int) ($e->attended_count ?? 0),
        ];
    }
}
