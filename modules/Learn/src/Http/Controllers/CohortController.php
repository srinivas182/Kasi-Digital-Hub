<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Hubs\HubSessions;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Learn\Models\Cohort;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Services\Cohorts;
use Modules\Learn\Services\CurrentProvider;

/**
 * Cohorts: providers create and run them (dashboard, sessions, members, nudges); hub managers approve
 * cohorts at their hub; learners join by code.
 */
final class CohortController
{
    public function __construct(private readonly CurrentProvider $providers, private readonly Cohorts $cohorts) {}

    public function index(Request $request): Response
    {
        [, $provider] = $this->providerAdmin($request);

        return Inertia::render('Learn/Cohorts/Index', [
            'cohorts' => Cohort::query()->with(['course', 'hub'])->where('organisation_id', $provider->id)->latest('starts_on')->get()
                ->map(fn (Cohort $c): array => $this->card($c)),
            'courses' => Course::query()->where('organisation_id', $provider->id)->where('status', 'published')->orderBy('title')->get(['id', 'title']),
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $provider] = $this->providerAdmin($request);
        $data = $request->validate([
            'course_id' => ['required', 'string', Rule::exists('learn_courses', 'id')->where('organisation_id', $provider->id)],
            'name' => ['required', 'string', 'max:120'],
            'hub_id' => ['nullable', 'string', 'exists:hubs,id'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            $cohort = $this->cohorts->create(Course::query()->whereKey($data['course_id'])->firstOrFail(), $provider, [
                'name' => $data['name'], 'hub_id' => $data['hub_id'] ?? null, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'capacity' => (int) $data['capacity'],
            ], $user);
        } catch (DomainException $e) {
            return back()->withErrors(['course_id' => $e->getMessage()]);
        }

        return to_route('learn.cohorts.show', $cohort)->with('status', __($cohort->status === 'pending_hub' ? 'learn.cohort.waiting_hub' : 'learn.cohort.created'));
    }

    public function show(Request $request, Cohort $cohort): Response
    {
        $this->canRun($request, $cohort);
        $cohort->load(['course', 'hub']);
        $members = DB::table('learn_cohort_members')->join('users', 'users.id', '=', 'learn_cohort_members.user_id')->where('cohort_id', $cohort->id)
            ->where('learn_cohort_members.status', '!=', 'removed')->orderBy('learn_cohort_members.joined_at')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'learn_cohort_members.status', 'learn_cohort_members.nudged_at']);
        $enrolments = Enrolment::query()->with('course')->where('course_id', $cohort->course_id)->whereIn('user_id', $members->pluck('id'))->get()->keyBy('user_id');
        $sessions = DB::table('learn_cohort_sessions')->where('cohort_id', $cohort->id)->orderBy('starts_at')->get();

        return Inertia::render('Learn/Cohorts/Show', [
            'cohort' => $this->card($cohort),
            'members' => $members->map(function (object $m) use ($enrolments): array {
                $e = $enrolments[$m->id] ?? null;
                $attendance = $e !== null ? $this->cohorts->attendance($e) : null;

                return ['id' => (string) $m->id, 'name' => $m->first_name.' '.mb_substr((string) $m->last_name, 0, 1).'.', 'status' => (string) $m->status,
                    'progress' => $e !== null ? $e->progress : 0, 'completed' => $e?->completed_at !== null, 'attendance' => $attendance['percent'] ?? null,
                    'stuck' => $e !== null && $e->completed_at === null && CarbonImmutable::parse((string) $e->getAttribute('updated_at'))->lt(now()->subDays(7)),
                    'nudgedAt' => $m->nudged_at !== null ? (string) $m->nudged_at : null];
            })->values(),
            'sessions' => $sessions->map(fn (object $s): array => ['id' => (int) $s->id, 'title' => (string) $s->title, 'startsAt' => CarbonImmutable::parse((string) $s->starts_at)->toIso8601String(),
                'cancelled' => (bool) $s->cancelled, 'attended' => $s->event_id !== null && app()->bound(HubSessions::class) ? count(app(HubSessions::class)->attended((string) $s->event_id)) : null])->values(),
            'joinUrl' => url('/learn/join/'.$cohort->code),
        ]);
    }

    public function addSession(Request $request, Cohort $cohort): RedirectResponse
    {
        [$user] = $this->canRun($request, $cohort);
        abort_unless($cohort->status === 'open', 409);
        $data = $request->validate(['title' => ['required', 'string', 'max:140'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'minutes' => ['required', 'integer', 'min:15', 'max:480'], 'room' => ['nullable', 'string', 'max:80']]);
        $starts = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], 'Africa/Johannesburg');
        abort_if($starts === null, 422);
        $this->cohorts->addSession($cohort->load('course'), $data['title'], $starts, $starts->addMinutes((int) $data['minutes']), $data['room'] ?? null, $user);

        return back()->with('status', __('learn.cohort.session_added'));
    }

    public function addMember(Request $request, Cohort $cohort): RedirectResponse
    {
        [$user] = $this->canRun($request, $cohort);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = SaFormat::normalisePhone($data['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;
        if ($person === null) {
            return back()->withErrors(['phone' => __('work.team.no_account')]);
        }

        try {
            $status = $this->cohorts->join($cohort->load('course'), $person, $user);
        } catch (DomainException $e) {
            return back()->withErrors(['phone' => $e->getMessage() === 'consent' ? __('learn.cohort.needs_consent') : $e->getMessage()]);
        }

        return back()->with('status', __('learn.cohort.member_'.$status, ['name' => $person->fullName()]));
    }

    public function removeMember(Request $request, Cohort $cohort, User $member): RedirectResponse
    {
        [$user] = $this->canRun($request, $cohort);
        $this->cohorts->remove($cohort->load('course'), $member, $user);

        return back()->with('status', __('learn.cohort.member_removed'));
    }

    public function nudge(Request $request, Cohort $cohort): RedirectResponse
    {
        [$user] = $this->canRun($request, $cohort);
        $data = $request->validate(['users' => ['required', 'array', 'min:1', 'max:500'], 'users.*' => ['string'], 'message' => ['required', 'string', 'min:5', 'max:300']]);
        $sent = $this->cohorts->nudge($cohort->load('course'), array_values($data['users']), $data['message'], $user);

        return back()->with('status', __('learn.cohort.nudged', ['count' => $sent]));
    }

    /** Hub managers: cohorts waiting for approval at their hubs. */
    public function approvals(Request $request): Response
    {
        $user = $this->user($request);
        $hubIds = RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['hub_manager', 'hub_owner'])->where('scope_type', 'hub')->pluck('scope_id');
        abort_if($hubIds->isEmpty(), 403);

        return Inertia::render('Learn/Cohorts/Approvals', [
            'cohorts' => Cohort::query()->with(['course.organisation', 'hub'])->whereIn('hub_id', $hubIds)->where('status', 'pending_hub')->get()
                ->map(fn (Cohort $c): array => [...$this->card($c), 'provider' => $c->course->organisation->displayName()]),
        ]);
    }

    public function decide(Request $request, Cohort $cohort): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['approve' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:300']]);

        try {
            $this->cohorts->decide($cohort, $user, (bool) $data['approve'], $data['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['cohort' => $e->getMessage()]);
        }

        return back()->with('status', __('learn.cohort.decided'));
    }

    /** Learners: join a cohort by code (QR codes link here). */
    public function joinForm(Request $request, ?string $code = null): Response
    {
        $cohort = $code !== null ? Cohort::query()->with(['course.organisation', 'hub'])->where('code', strtoupper($code))->first() : null;

        return Inertia::render('Learn/Cohorts/Join', ['code' => $code !== null ? strtoupper($code) : '', 'cohort' => $cohort !== null ? [...$this->card($cohort), 'provider' => $cohort->course->organisation->displayName()] : null]);
    }

    public function join(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:8'], 'consent' => ['boolean']]);
        $cohort = Cohort::query()->with('course')->where('code', strtoupper(trim($data['code'])))->first();
        if ($cohort === null) {
            return back()->withErrors(['code' => __('learn.cohort.unknown_code')]);
        }
        if ($request->boolean('consent')) {
            app(ConsentService::class)->record($user, ['learning_records' => true]);
        }

        try {
            $status = $this->cohorts->join($cohort, $user);
        } catch (DomainException $e) {
            return back()->withErrors($e->getMessage() === 'consent' ? ['consent' => __('learn.enrol.consent_needed')] : ['code' => $e->getMessage()]);
        }

        return to_route('learn.my')->with('status', __('learn.cohort.joined_'.$status, ['name' => $cohort->name]));
    }

    /** @return array<string, mixed> */
    private function card(Cohort $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'course' => $c->course->title, 'hub' => $c->hub?->name, 'status' => $c->status,
            'reason' => $c->status_reason, 'startsOn' => $c->starts_on->toDateString(), 'endsOn' => $c->ends_on->toDateString(), 'capacity' => $c->capacity,
            'members' => DB::table('learn_cohort_members')->where('cohort_id', $c->id)->where('status', 'joined')->count(),
            'waiting' => DB::table('learn_cohort_members')->where('cohort_id', $c->id)->where('status', 'waiting')->count()];
    }

    /** @return array{0: User, 1: Organisation} */
    private function providerAdmin(Request $request): array
    {
        $user = $this->user($request);
        $provider = $this->providers->resolve($request, $user);
        abort_unless($provider !== null && $this->providers->has($user, $provider, 'provider_admin'), 403);

        return [$user, $provider];
    }

    /**
     * Provider staff of the course's provider, and the hub's manager/facilitators.
     *
     * @return array{0: User}
     */
    private function canRun(Request $request, Cohort $cohort): array
    {
        $user = $this->user($request);
        $provider = $this->providers->all($user)->firstWhere('id', $cohort->organisation_id);
        $hubStaff = $cohort->hub_id !== null && RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['hub_manager', 'hub_owner', 'hub_facilitator'])
            ->where('scope_type', 'hub')->where('scope_id', $cohort->hub_id)->exists();
        abort_unless($provider !== null || $hubStaff, 404);

        return [$user];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
