<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Hubs\HubSessions;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Learn\Models\Cohort;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Notifications\LearnerNotification;

/**
 * Cohorts (ADR-023): groups taking a course together, joined by code/QR or added by a facilitator,
 * with a waiting list. Cohorts at a hub need the hub manager's approval. Sessions are hub sessions
 * (hub events), so sign-ups, reminders and attendance are reused; attendance counts for blended courses.
 */
final readonly class Cohorts
{
    public function __construct(private Learning $learning, private Notifier $notifier, private AuditLogger $audit) {}

    /** @param array{name: string, hub_id: string|null, starts_on: string, ends_on: string, capacity: int} $data */
    public function create(Course $course, Organisation $provider, array $data, User $by): Cohort
    {
        if ($course->organisation_id !== $provider->id || $course->status !== 'published') {
            throw new DomainException(__('learn.cohort.course_not_ready'));
        }

        $cohort = Cohort::query()->create([...$data, 'course_id' => $course->id, 'organisation_id' => $provider->id, 'created_by' => $by->id,
            'code' => self::code(), 'status' => $data['hub_id'] !== null ? 'pending_hub' : 'open']);
        $this->audit->record('learn.cohort_created', meta: ['cohort' => $cohort->id], actor: $by);

        if ($cohort->hub_id !== null) {
            $managers = RoleAssignment::query()->where('role', 'hub_manager')->where('scope_type', 'hub')->where('scope_id', $cohort->hub_id)->pluck('user_id');
            User::query()->whereIn('id', $managers)->get()->each(fn (User $m) => $this->notifier->send($m, new LearnerNotification('cohort_approval', ['title' => $course->title], '/learn/cohorts/approvals')));
        }

        return $cohort;
    }

    public function canApprove(User $user, Cohort $cohort): bool
    {
        return $cohort->hub_id !== null && RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['hub_manager', 'hub_owner'])
            ->where('scope_type', 'hub')->where('scope_id', $cohort->hub_id)->exists();
    }

    public function decide(Cohort $cohort, User $manager, bool $approve, ?string $reason = null): void
    {
        if ($cohort->status !== 'pending_hub' || ! $this->canApprove($manager, $cohort)) {
            throw new DomainException(__('learn.cohort.cannot_approve'));
        }
        $cohort->forceFill(['status' => $approve ? 'open' : 'cancelled', 'status_reason' => $reason, 'approved_by' => $manager->id])->save();
        $this->audit->record('learn.cohort_'.($approve ? 'approved' : 'declined'), meta: ['cohort' => $cohort->id], actor: $manager);
    }

    /** Join by code (or added by a facilitator). Full cohorts put people on the waiting list. */
    public function join(Cohort $cohort, User $person, ?User $by = null): string
    {
        if ($cohort->status !== 'open' || $cohort->ends_on->isPast()) {
            throw new DomainException(__('learn.cohort.closed'));
        }
        $existing = DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('user_id', $person->id)->first();
        if ($existing !== null && $existing->status !== 'removed') {
            return (string) $existing->status;
        }

        $enrolment = $this->learning->enrol($person, $cohort->course); // age and consent rules apply
        $full = DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('status', 'joined')->count() >= $cohort->capacity;
        $status = $full ? 'waiting' : 'joined';
        DB::table('learn_cohort_members')->updateOrInsert(['cohort_id' => $cohort->id, 'user_id' => $person->id], ['status' => $status, 'added_by' => $by?->id, 'joined_at' => now()]);
        if ($status === 'joined') {
            $this->registerForSessions($cohort, $person);
        }
        $this->audit->record('learn.cohort_joined', $person, meta: ['cohort' => $cohort->id, 'status' => $status, 'enrolment' => $enrolment->id], actor: $by);

        return $status;
    }

    public function remove(Cohort $cohort, User $person, User $by): void
    {
        DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('user_id', $person->id)->update(['status' => 'removed']);
        $next = DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('status', 'waiting')->orderBy('joined_at')->first();
        if ($next !== null) {
            DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('user_id', $next->user_id)->update(['status' => 'joined']);
            $promoted = User::query()->whereKey($next->user_id)->first();
            if ($promoted !== null) {
                $this->registerForSessions($cohort, $promoted);
                $this->notifier->send($promoted, new LearnerNotification('cohort_place', ['title' => $cohort->course->title], '/learn/my'));
            }
        }
        $this->audit->record('learn.cohort_removed', $person, meta: ['cohort' => $cohort->id], actor: $by);
    }

    public function addSession(Cohort $cohort, string $title, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $room, User $by): int
    {
        $eventId = null;
        if ($cohort->hub_id !== null && app()->bound(HubSessions::class)) {
            $sessions = app(HubSessions::class);
            $eventId = $sessions->schedule($cohort->hub_id, $title, $cohort->course->title.' - '.$cohort->name, $startsAt, $endsAt, $cohort->capacity, $room, $by);
            foreach ($this->memberUsers($cohort) as $member) {
                $sessions->register($eventId, $member);
            }
        }

        return (int) DB::table('learn_cohort_sessions')->insertGetId(['cohort_id' => $cohort->id, 'event_id' => $eventId, 'title' => $title, 'starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc()]);
    }

    /**
     * Attendance for blended or hub courses: sessions attended / sessions held so far.
     *
     * @return array{held: int, attended: int, percent: int}|null null when attendance doesn't apply
     */
    public function attendance(Enrolment $enrolment): ?array
    {
        $course = $enrolment->course;
        if (! in_array($course->delivery, ['blended', 'hub'], true)) {
            return null;
        }
        $cohortIds = DB::table('learn_cohort_members')->join('learn_cohorts', 'learn_cohorts.id', '=', 'learn_cohort_members.cohort_id')
            ->where('learn_cohorts.course_id', $course->id)->where('learn_cohort_members.user_id', $enrolment->user_id)->where('learn_cohort_members.status', 'joined')
            ->pluck('learn_cohorts.id');
        $sessions = DB::table('learn_cohort_sessions')->whereIn('cohort_id', $cohortIds)->where('cancelled', false)->whereNotNull('event_id');
        if (! (clone $sessions)->exists()) {
            return null; // no hub sessions: attendance doesn't apply
        }
        $held = (clone $sessions)->where('ends_at', '<=', now())->pluck('event_id');
        if ($held->isEmpty()) {
            return ['held' => 0, 'attended' => 0, 'percent' => 0]; // sessions still to come: not complete yet
        }

        $attended = 0;
        foreach ($held as $eventId) {
            $attended += in_array($enrolment->user_id, app(HubSessions::class)->attended((string) $eventId), true) ? 1 : 0;
        }

        return ['held' => $held->count(), 'attended' => $attended, 'percent' => (int) floor(100 * $attended / $held->count())];
    }

    /** @param list<string> $userIds */
    public function nudge(Cohort $cohort, array $userIds, string $message, User $by): int
    {
        $sent = 0;
        User::query()->whereIn('id', DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('status', 'joined')->whereIn('user_id', $userIds)->pluck('user_id'))->get()
            ->each(function (User $u) use ($cohort, $message, &$sent): void {
                $this->notifier->send($u, new LearnerNotification('nudge', ['title' => $cohort->course->title, 'message' => $message], '/learn/my'));
                DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('user_id', $u->id)->update(['nudged_at' => now()]);
                $sent++;
            });
        $this->audit->record('learn.cohort_nudged', meta: ['cohort' => $cohort->id, 'count' => $sent], actor: $by);

        return $sent;
    }

    /** @return list<User> */
    private function memberUsers(Cohort $cohort): array
    {
        return array_values(User::query()->whereIn('id', DB::table('learn_cohort_members')->where('cohort_id', $cohort->id)->where('status', 'joined')->pluck('user_id'))->get()->all());
    }

    private function registerForSessions(Cohort $cohort, User $person): void
    {
        if (! app()->bound(HubSessions::class)) {
            return;
        }
        DB::table('learn_cohort_sessions')->where('cohort_id', $cohort->id)->where('cancelled', false)->where('starts_at', '>', now())->whereNotNull('event_id')->pluck('event_id')
            ->each(fn ($eventId) => app(HubSessions::class)->register((string) $eventId, $person));
    }

    private static function code(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Cohort::query()->where('code', $code)->exists() || preg_match('/[0O1IL]/', $code) === 1);

        return $code;
    }
}
