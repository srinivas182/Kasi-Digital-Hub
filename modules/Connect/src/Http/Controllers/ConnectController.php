<?php

declare(strict_types=1);

namespace Modules\Connect\Http\Controllers;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Models\Mentorship;
use Modules\Connect\Services\Matching;
use Modules\Connect\Services\Mentoring;
use Modules\Connect\Services\Mentors;
use Modules\Connect\Services\Safeguarding;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Hubs\HubSessions;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KasiConnect for young people and mentors: find a mentor, requests, mentorships (agreement, sessions,
 * notes, messages, ending), mentor profile, group sessions, hours certificate, and reporting a concern.
 */
final class ConnectController
{
    public function home(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Connect/Home', [
            'mentorships' => Mentorship::query()->where(fn ($q) => $q->where('mentee_id', $user->id)->orWhere('mentor_id', $user->id))->latest('updated_at')->get()
                ->map(fn (Mentorship $m): array => $this->card($m, $user)),
            'pending' => DB::table('connect_requests')->join('users', 'users.id', '=', 'connect_requests.mentor_id')->where('mentee_id', $user->id)->where('connect_requests.status', 'pending')
                ->get(['connect_requests.id', 'users.first_name', 'connect_requests.goal'])->map(static fn (object $r): array => ['id' => (string) $r->id, 'mentor' => (string) $r->first_name, 'goal' => (string) $r->goal]),
            'isMentor' => Mentor::query()->whereKey($user->id)->exists(),
            'minor' => $user->isMinor(),
        ]);
    }

    public function find(Request $request, Matching $matching): Response
    {
        $user = $this->user($request);
        $wish = ['goal' => (string) $request->query('goal', 'job'), 'interests' => (string) $request->query('interests', ''),
            'languages' => array_values(array_filter(explode(',', (string) $request->query('languages', 'English')))), 'format' => is_string($request->query('format')) && $request->query('format') !== '' ? (string) $request->query('format') : null];
        if (! in_array($wish['goal'], Mentor::GOALS, true)) {
            $wish['goal'] = 'job';
        }

        return Inertia::render('Connect/Find', [
            'wish' => $wish, 'minor' => $user->isMinor(),
            'suggestions' => array_map(static fn (array $s): array => ['id' => $s['mentor']->user_id, 'name' => $s['mentor']->user->first_name.' '.mb_substr((string) $s['mentor']->user->last_name, 0, 1).'.',
                'headline' => $s['mentor']->headline, 'bio' => $s['mentor']->bio, 'formats' => $s['mentor']->formats, 'languages' => $s['mentor']->languages,
                'score' => $s['score'], 'reasons' => $s['reasons']], $matching->suggest($user, $wish)),
        ]);
    }

    public function request(Request $request, Mentor $mentor, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['goal' => ['required', Rule::in(Mentor::GOALS)], 'message' => ['nullable', 'string', 'max:1000']]);
        try {
            $mentoring->request($user, $mentor->load('user'), $data['goal'], $data['message'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['request' => $e->getMessage()]);
        }

        return to_route('connect.home')->with('status', __('connect.request.sent'));
    }

    public function withdrawRequest(Request $request, string $id): RedirectResponse
    {
        $user = $this->user($request);
        DB::table('connect_requests')->where('id', $id)->where('mentee_id', $user->id)->where('status', 'pending')->update(['status' => 'withdrawn', 'updated_at' => now()]);

        return back();
    }

    public function show(Request $request, Mentorship $mentorship): Response
    {
        $user = $this->party($request, $mentorship);
        $isMentor = $user->id === $mentorship->mentor_id;
        $mentee = User::query()->findOrFail($mentorship->mentee_id);
        $mentor = User::query()->findOrFail($mentorship->mentor_id);

        return Inertia::render('Connect/Mentorship', [
            'mentorship' => [...$this->card($mentorship, $user), 'goals' => $mentorship->goals, 'perMonth' => $mentorship->per_month, 'months' => $mentorship->months,
                'format' => $mentorship->format, 'mentorAgreed' => $mentorship->mentor_agreed_at !== null, 'menteeAgreed' => $mentorship->mentee_agreed_at !== null,
                'startsOn' => $mentorship->starts_on?->toDateString(), 'endsOn' => $mentorship->ends_on?->toDateString(), 'renewed' => $mentorship->renewed,
                'myFeedback' => $isMentor ? $mentorship->mentor_feedback : $mentorship->mentee_feedback],
            'isMentor' => $isMentor,
            'menteeMinor' => $mentee->isMinor(),
            'otherId' => $isMentor ? $mentee->id : $mentor->id,
            'sessions' => DB::table('connect_sessions')->where('mentorship_id', $mentorship->id)->orderBy('starts_at')->get()
                ->map(static fn (object $s): array => ['id' => (int) $s->id, 'startsAt' => CarbonImmutable::parse((string) $s->starts_at)->toIso8601String(), 'minutes' => (int) $s->minutes,
                    'format' => (string) $s->format, 'link' => $s->link, 'status' => (string) $s->status, 'sharedNotes' => $s->shared_notes,
                    'actions' => (array) json_decode((string) ($s->actions ?? '[]'), true), 'privateNotes' => $isMentor ? $s->private_notes : null,
                    'helpful' => $s->helpful === null ? null : (bool) $s->helpful])->values(),
            'messages' => $mentee->isMinor() ? [] : DB::table('connect_messages')->where('mentorship_id', $mentorship->id)->orderBy('created_at')->get()
                ->filter(static fn (object $m): bool => ! $m->held || $m->sender_id === $user->id)
                ->map(static fn (object $m): array => ['id' => (string) $m->id, 'body' => (string) $m->body, 'mine' => $m->sender_id === $user->id, 'held' => (bool) $m->held, 'at' => (string) $m->created_at])->values(),
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function propose(Request $request, Mentorship $mentorship, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->party($request, $mentorship);
        $data = $request->validate(['goals' => ['required', 'array', 'min:1', 'max:3'], 'goals.*' => ['string', 'max:200'], 'per_month' => ['required', 'integer', 'min:1', 'max:4'],
            'months' => ['required', 'integer', 'min:1', 'max:6'], 'format' => ['required', Rule::in(Mentor::FORMATS)]]);

        return $this->attempt(fn () => $mentoring->propose($mentorship, $user, ['goals' => $data['goals'], 'per_month' => (int) $data['per_month'], 'months' => (int) $data['months'], 'format' => $data['format']]), 'connect.agreement.sent');
    }

    public function agree(Request $request, Mentorship $mentorship, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->party($request, $mentorship);

        return $this->attempt(fn () => $mentoring->agree($mentorship, $user), 'connect.agreement.agreed');
    }

    public function schedule(Request $request, Mentorship $mentorship, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->party($request, $mentorship);
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'], 'minutes' => ['required', 'integer', 'min:15', 'max:180'],
            'format' => ['required', Rule::in(Mentor::FORMATS)], 'link' => ['nullable', 'url:https', 'max:255'], 'hub_id' => ['nullable', 'string', 'exists:hubs,id'], 'room' => ['nullable', 'string', 'max:80']]);
        $starts = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], 'Africa/Johannesburg');
        abort_if($starts === null, 422);

        return $this->attempt(fn () => $mentoring->schedule($mentorship, $user, $starts, (int) $data['minutes'], $data['format'], $data['link'] ?? null, $data['hub_id'] ?? null, $data['room'] ?? null), 'connect.session.scheduled');
    }

    public function record(Request $request, int $session, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['shared_notes' => ['required', 'string', 'max:4000'], 'actions' => ['array', 'max:10'], 'actions.*' => ['nullable', 'string', 'max:200'], 'private_notes' => ['nullable', 'string', 'max:4000']]);

        return $this->attempt(fn () => $mentoring->record($session, $user, $data['shared_notes'], array_values(array_map('strval', $data['actions'] ?? [])), $data['private_notes'] ?? null), 'connect.session.recorded');
    }

    public function helpful(Request $request, int $session, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['helpful' => ['required', 'boolean']]);

        return $this->attempt(fn () => $mentoring->helpful($session, $user, (bool) $data['helpful']), 'partner.refer.thanks');
    }

    public function message(Request $request, Mentorship $mentorship, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->party($request, $mentorship);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        try {
            $result = $mentoring->message($mentorship, $user, $data['body']);
        } catch (DomainException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
        if (! $result['sent'] && $result['reason'] === 'contact') {
            return back()->withErrors(['message' => __('connect.messages.no_contact', ['count' => Mentoring::CONTACT_FREE_SESSIONS])]);
        }

        return back()->with('status', __(match ($result['reason']) {
            'held' => 'work.messages.held', 'contact_reminder' => 'connect.messages.contact_reminder', default => 'work.messages.sent'
        }));
    }

    public function end(Request $request, Mentorship $mentorship, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->party($request, $mentorship);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300'], 'goals_met' => ['array'], 'goals_met.*' => ['boolean'], 'feedback' => ['nullable', 'string', 'max:1000']]);
        $mentoring->end($mentorship, $user, $data['reason'], array_values(array_map('boolval', $data['goals_met'] ?? [])), $data['feedback'] ?? null);

        return back()->with('status', __('connect.ended'));
    }

    public function renew(Request $request, Mentorship $mentorship, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->party($request, $mentorship);

        return $this->attempt(fn () => $mentoring->renew($mentorship, $user), 'connect.agreement.renewed');
    }

    public function concern(Request $request, Safeguarding $safeguarding): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['about' => ['nullable', 'string', 'exists:users,id'], 'mentorship_id' => ['nullable', 'string'], 'kind' => ['required', Rule::in(Safeguarding::KINDS)],
            'urgent' => ['boolean'], 'body' => ['required', 'string', 'min:10', 'max:4000']]);
        if (($data['mentorship_id'] ?? null) !== null) {
            abort_unless(Mentorship::query()->whereKey($data['mentorship_id'])->first()?->involves($user->id) === true, 404);
        }
        $safeguarding->report($user, $data['about'] ?? null, $data['mentorship_id'] ?? null, $data['kind'], (bool) ($data['urgent'] ?? false), $data['body']);

        return back()->with('status', __('connect.concern.received'));
    }

    // --- Mentors -------------------------------------------------------------------------------

    public function mentorProfile(Request $request, Mentors $mentors): Response
    {
        $user = $this->user($request);
        $mentor = Mentor::query()->find($user->id);

        return Inertia::render('Connect/MentorProfile', [
            'minor' => $user->isMinor(),
            'mentor' => $mentor === null ? null : [...$mentor->only(['headline', 'bio', 'expertise', 'goals', 'languages', 'formats', 'hub_id', 'availability', 'capacity', 'status', 'status_reason', 'minors_ok']),
                'missing' => $mentors->missing($mentor), 'hours' => round($mentor->minutes_mentored / 60, 1)],
            'requests' => DB::table('connect_requests')->join('users', 'users.id', '=', 'connect_requests.mentee_id')->where('mentor_id', $user->id)->where('connect_requests.status', 'pending')
                ->get(['connect_requests.id', 'users.first_name', 'connect_requests.goal', 'connect_requests.message'])
                ->map(static fn (object $r): array => ['id' => (string) $r->id, 'name' => (string) $r->first_name, 'goal' => (string) $r->goal, 'message' => $r->message]),
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'groups' => DB::table('connect_group_sessions')->where('mentor_id', $user->id)->orderByDesc('starts_at')->limit(10)->get(['title', 'starts_at', 'minutes']),
        ]);
    }

    public function saveMentor(Request $request, Mentors $mentors): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'headline' => ['required', 'string', 'max:140'], 'bio' => ['nullable', 'string', 'max:2000'],
            'expertise' => ['required', 'array', 'min:1', 'max:12'], 'expertise.*' => ['string', 'max:80'],
            'goals' => ['required', 'array', 'min:1'], 'goals.*' => [Rule::in(Mentor::GOALS)],
            'languages' => ['required', 'array', 'min:1'], 'languages.*' => ['string', 'max:40'],
            'formats' => ['required', 'array', 'min:1'], 'formats.*' => [Rule::in(Mentor::FORMATS)],
            'hub_id' => ['nullable', 'string', 'exists:hubs,id'], 'availability' => ['nullable', 'string', 'max:200'], 'capacity' => ['required', 'integer', 'min:1', 'max:5'],
        ]);
        try {
            $mentors->apply($user, [...$data, 'municipality_id' => $user->municipality_id]);
        } catch (DomainException $e) {
            return back()->withErrors(['headline' => $e->getMessage()]);
        }

        return back()->with('status', __('connect.mentor.saved'));
    }

    public function answer(Request $request, string $id, Mentoring $mentoring): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        try {
            $m = $mentoring->answer($id, $user, (bool) $data['accept']);
        } catch (DomainException $e) {
            return back()->withErrors(['request' => $e->getMessage()]);
        }

        return $m !== null ? to_route('connect.mentorship', $m) : back();
    }

    public function groupSession(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $mentor = Mentor::query()->whereKey($user->id)->where('status', 'approved')->firstOrFail();
        $data = $request->validate(['title' => ['required', 'string', 'max:140'], 'hub_id' => ['required', 'string', 'exists:hubs,id'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'minutes' => ['required', 'integer', 'min:30', 'max:240'], 'capacity' => ['required', 'integer', 'min:2', 'max:200']]);
        $starts = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], 'Africa/Johannesburg');
        abort_if($starts === null || ! app()->bound(HubSessions::class), 422);
        $eventId = app(HubSessions::class)->schedule($data['hub_id'], $data['title'], __('connect.group.description', ['name' => $user->first_name]), $starts, $starts->addMinutes((int) $data['minutes']), (int) $data['capacity'], null, $user);
        DB::table('connect_group_sessions')->insert(['mentor_id' => $mentor->user_id, 'hub_id' => $data['hub_id'], 'event_id' => $eventId, 'title' => $data['title'], 'starts_at' => $starts->utc(), 'minutes' => (int) $data['minutes'], 'created_at' => now()]);

        return back()->with('status', __('connect.group.created'));
    }

    /** Verifiable certificate of volunteer mentoring hours. */
    public function hours(Request $request, DocumentIssuer $issuer): StreamedResponse
    {
        $user = $this->user($request);
        $mentor = Mentor::query()->whereKey($user->id)->firstOrFail();
        abort_if($mentor->minutes_mentored < 60, 404);
        $document = $issuer->issue(owner: $user, type: 'connect_hours', title: 'Mentoring hours - '.$user->fullName(), view: 'connect::hours',
            data: ['name' => $user->fullName(), 'hours' => round($mentor->minutes_mentored / 60, 1), 'mentees' => Mentorship::query()->where('mentor_id', $user->id)->whereIn('status', ['active', 'ended'])->count(),
                'since' => $mentor->getAttribute('approved_at') ?? $mentor->getAttribute('created_at')], templateVersion: 1, subject: ['connect_mentor', $user->id]);

        return $issuer->download($document);
    }

    /** @return array<string, mixed> */
    private function card(Mentorship $m, User $viewer): array
    {
        $other = User::query()->find($viewer->id === $m->mentor_id ? $m->mentee_id : $m->mentor_id);

        return ['id' => $m->id, 'status' => $m->status, 'goal' => $m->goal, 'role' => $viewer->id === $m->mentor_id ? 'mentor' : 'mentee',
            'with' => $other !== null ? $other->first_name.' '.mb_substr((string) $other->last_name, 0, 1).'.' : '-'];
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['mentorship' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function party(Request $request, Mentorship $mentorship): User
    {
        $user = $this->user($request);
        abort_unless($mentorship->involves($user->id), 404);

        return $user;
    }
}
