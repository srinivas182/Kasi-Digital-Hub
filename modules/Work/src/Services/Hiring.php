<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Work\Events\ApplicationStageChanged;
use Modules\Work\Events\ApplicationSubmitted;
use Modules\Work\Events\HireConfirmed;
use Modules\Work\Events\RetentionChecked;
use Modules\Work\Models\Application;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\WorkCv;
use Modules\Work\Notifications\Messages\ApplicantNotification;
use Modules\Work\Notifications\Messages\EmployerNotification;

/**
 * Applications from "apply" to "hired" (ADR-020): stage changes with a timeline, interviews,
 * messages checked for scams, "no ghosting", hires confirmed by both sides and retention check-ins.
 */
final readonly class Hiring
{
    public const DAILY_APPLICATIONS = 20;

    public const NO_GHOSTING_DAYS = 7;

    public function __construct(
        private Notifier $notifier,
        private AuditLogger $audit,
        private ModerationService $moderation,
        private CvComposer $cvs,
    ) {}

    /**
     * @param  array<int, string>  $answers  index of screening question => answer
     */
    public function apply(JobListing $listing, User $person, ?string $cvId, array $answers, ?string $message, ?User $by = null): Application
    {
        if ($listing->status !== 'live' || $listing->closes_on->lt(CarbonImmutable::now('Africa/Johannesburg')->startOfDay())) {
            throw new DomainException(__('work.apply.closed'));
        }
        if (Application::query()->where('listing_id', $listing->id)->where('user_id', $person->id)->exists()) {
            throw new DomainException(__('work.apply.already'));
        }
        if (Application::query()->where('user_id', $person->id)->where('created_at', '>=', now()->subDay())->count() >= self::DAILY_APPLICATIONS) {
            throw new DomainException(__('work.apply.limit', ['limit' => self::DAILY_APPLICATIONS]));
        }

        $cv = $cvId !== null ? WorkCv::query()->where('user_id', $person->id)->whereKey($cvId)->first() : null;
        $cv ??= $this->cvs->create($person, 'classic', $by); // make one from the profile on the spot

        $questions = $listing->questions()->get();
        $pairs = [];
        foreach ($questions as $i => $question) {
            $pairs[] = ['question' => $question->question, 'answer' => mb_substr(trim((string) ($answers[$i] ?? '')), 0, 300)];
        }

        $application = DB::transaction(function () use ($listing, $person, $cv, $pairs, $message, $by): Application {
            $application = Application::query()->create([
                'listing_id' => $listing->id, 'user_id' => $person->id, 'cv_id' => $cv->id, 'stage' => 'new', 'answers' => $pairs,
                'message' => $message !== null ? mb_substr(trim($message), 0, 500) : null, 'reference' => strtoupper(Str::random(4)),
                'score' => DB::table('work_matches')->where('listing_id', $listing->id)->where('user_id', $person->id)->value('score'),
                'assisted_by' => $by?->id, 'stage_changed_at' => now(),
            ]);
            $this->event($application, 'submitted', 'new', $by ?? $person);
            DB::table('work_invitations')->where('listing_id', $listing->id)->where('user_id', $person->id)->where('status', 'sent')
                ->update(['status' => 'accepted', 'responded_at' => now(), 'updated_at' => now()]);

            return $application;
        });

        $this->audit->record('work.applied', $person, meta: ['listing' => $listing->id, 'application' => $application->id], actor: $by);
        event(new ApplicationSubmitted($person, $by?->id, ['listing' => $listing->id, 'application' => $application->id]));
        $this->tellEmployer($listing, 'new_application', ['title' => $listing->title], "/work/employer/listings/{$listing->id}/applicants", 'new-app:'.$listing->id.':'.now()->format('YmdH'));

        return $application;
    }

    public function withdraw(Application $application, User $person): void
    {
        if (! in_array($application->stage, Application::OPEN, true)) {
            throw new DomainException(__('work.apply.not_open'));
        }

        $application->forceFill(['stage' => 'withdrawn', 'stage_changed_at' => now()])->save();
        $this->event($application, 'withdrawn', 'withdrawn', $person);
        $this->audit->record('work.application_withdrawn', $person, meta: ['application' => $application->id]);
        $this->tellEmployer($application->listing, 'withdrawn', ['title' => $application->listing->title, 'reference' => $application->reference], "/work/employer/listings/{$application->listing_id}/applicants");
    }

    /**
     * Move one or more applications to a stage. "Not successful" sends a kind message
     * (the employer's own text or the template) - the private reason is never shown.
     */
    public function move(Application $application, string $stage, User $by, ?string $message = null, ?string $reason = null): void
    {
        if (! in_array($stage, Application::STAGES, true) || in_array($application->stage, ['withdrawn', 'hired'], true) && $stage !== $application->stage) {
            throw new DomainException(__('work.pipeline.cannot_move'));
        }
        if ($application->stage === $stage) {
            return;
        }

        $application->forceFill([
            'stage' => $stage, 'stage_changed_at' => now(),
            'reject_reason' => $stage === 'unsuccessful' ? $reason : null,
            'hired_on' => $stage === 'hired' ? now('Africa/Johannesburg')->toDateString() : $application->hired_on,
            'outcome_notified_at' => in_array($stage, ['unsuccessful', 'hired'], true) ? now() : $application->outcome_notified_at,
        ])->save();
        $this->event($application, 'stage', $stage, $by, array_filter(['message' => $message]));
        $this->audit->record('work.application_stage', $application->user, meta: ['application' => $application->id, 'stage' => $stage, 'reason' => $reason], actor: $by);
        event(new ApplicationStageChanged($application->user ?? $by, $by->id, ['application' => $application->id, 'stage' => $stage, 'listing' => $application->listing_id]));

        if ($application->user !== null && in_array($stage, ['shortlisted', 'interview', 'offer', 'hired', 'unsuccessful'], true)) {
            $params = ['title' => $application->listing->title, 'employer' => $application->listing->organisation->displayName(), 'message' => (string) $message];
            $this->notifier->send($application->user, new ApplicantNotification($stage, $params, '/work/applications/'.$application->id));

            if ($stage === 'hired') {
                $this->notifier->send($application->user, new ApplicantNotification('confirm_hire', $params, '/work/applications/'.$application->id, 'confirm-hire:'.$application->id));
            }
        }
    }

    /** The young person confirms they started (or not). Two-sided confirmation is what funders count. */
    public function confirmHire(Application $application, User $person, bool $started, ?string $startedOn = null): void
    {
        if ($application->stage !== 'hired' || $application->hire_confirmed_at !== null) {
            throw new DomainException(__('work.apply.not_open'));
        }

        if (! $started) {
            $this->event($application, 'hire_confirmed', null, $person, ['started' => false]);

            return;
        }

        $application->forceFill(['hire_confirmed_at' => now(), 'hired_on' => $startedOn ?? $application->hired_on])->save();
        $this->event($application, 'hire_confirmed', null, $person, ['started' => true]);
        foreach ([30, 90] as $days) {
            DB::table('work_retention_checks')->insertOrIgnore(['application_id' => $application->id, 'days' => $days,
                'due_on' => CarbonImmutable::parse($application->hired_on ?? now())->addDays($days)->toDateString()]);
        }
        event(new HireConfirmed($person, null, ['application' => $application->id, 'listing' => $application->listing_id, 'occupation' => (string) $application->listing->occupation_id]));
        $this->tellEmployer($application->listing, 'hire_confirmed', ['name' => $person->fullName(), 'title' => $application->listing->title], "/work/employer/listings/{$application->listing_id}/applicants");
    }

    public function answerRetention(int $checkId, User $person, bool $stillWorking): void
    {
        $check = DB::table('work_retention_checks')->join('work_applications', 'work_applications.id', '=', 'work_retention_checks.application_id')
            ->where('work_retention_checks.id', $checkId)->where('work_applications.user_id', $person->id)->first(['work_retention_checks.*']);
        if ($check === null || $check->answered_at !== null) {
            throw new DomainException(__('work.apply.not_open'));
        }

        DB::table('work_retention_checks')->where('id', $checkId)->update(['answer' => $stillWorking ? 'yes' : 'no', 'answered_at' => now()]);
        $application = Application::query()->whereKey($check->application_id)->firstOrFail();
        $this->event($application, 'retention', null, $person, ['days' => (int) $check->days, 'still_working' => $stillWorking]);
        event(new RetentionChecked($person, null, ['application' => $application->id, 'days' => (int) $check->days, 'still_working' => $stillWorking]));
    }

    public function note(Application $application, User $by, string $body): void
    {
        DB::table('work_application_notes')->insert(['application_id' => $application->id, 'author_id' => $by->id, 'body' => mb_substr(trim($body), 0, 2000), 'created_at' => now()]);
    }

    /** @param 'in_person'|'hub'|'phone'|'video' $mode */
    public function proposeInterview(Application $application, User $by, string $startsAt, string $mode, ?string $place, ?string $note): string
    {
        DB::table('work_interviews')->where('application_id', $application->id)->whereIn('status', ['proposed', 'reschedule_requested', 'confirmed'])
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        $id = (string) Str::ulid();
        DB::table('work_interviews')->insert(['id' => $id, 'application_id' => $application->id, 'starts_at' => CarbonImmutable::parse($startsAt, 'Africa/Johannesburg')->utc(),
            'mode' => $mode, 'place' => $place, 'note' => $note, 'status' => 'proposed', 'proposed_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]);
        if (in_array($application->stage, ['new', 'shortlisted'], true)) {
            $this->move($application, 'interview', $by);
        }
        $this->event($application, 'interview', null, $by, ['status' => 'proposed', 'starts_at' => $startsAt]);

        if ($application->user !== null) {
            $this->notifier->send($application->user, new ApplicantNotification('interview_proposed', [
                'title' => $application->listing->title, 'employer' => $application->listing->organisation->displayName(),
                'when' => SaFormat::dateTime(CarbonImmutable::parse($startsAt, 'Africa/Johannesburg')),
            ], '/work/applications/'.$application->id, 'interview:'.$id));
        }

        return $id;
    }

    /** @param 'confirmed'|'reschedule_requested'|'declined' $answer */
    public function answerInterview(string $interviewId, User $person, string $answer): void
    {
        $interview = DB::table('work_interviews')->where('id', $interviewId)->first();
        $application = $interview !== null ? Application::query()->whereKey($interview->application_id)->first() : null;
        if ($interview === null || $application === null || $application->user_id !== $person->id || $interview->status !== 'proposed') {
            throw new DomainException(__('work.apply.not_open'));
        }

        DB::table('work_interviews')->where('id', $interviewId)->update(['status' => $answer, 'updated_at' => now()]);
        $this->event($application, 'interview', null, $person, ['status' => $answer]);
        $kind = ['confirmed' => 'interview_confirmed', 'reschedule_requested' => 'interview_reschedule', 'declined' => 'interview_declined'][$answer];
        $this->tellEmployer($application->listing, $kind, ['title' => $application->listing->title, 'reference' => $application->reference], "/work/employer/listings/{$application->listing_id}/applicants/{$application->id}");
    }

    /**
     * Text-only messages; anything that looks like a scam or abuse is held for review.
     */
    public function message(Application $application, User $sender, bool $fromEmployer, string $body): bool
    {
        $id = (string) Str::ulid();
        $check = $this->moderation->check($body, 'work_message', $id, $sender, useAi: false);
        $held = $check['verdict'] === 'flag';

        DB::table('work_messages')->insert(['id' => $id, 'application_id' => $application->id, 'sender_id' => $sender->id, 'from_employer' => $fromEmployer,
            'body' => mb_substr(trim($body), 0, 2000), 'held' => $held, 'created_at' => now()]);

        if (! $held) {
            $params = ['title' => $application->listing->title, 'reference' => $application->reference, 'employer' => $application->listing->organisation->displayName()];
            if ($fromEmployer && $application->user !== null) {
                $this->notifier->send($application->user, new ApplicantNotification('message', $params, '/work/applications/'.$application->id, 'msg:'.$application->id.':'.now()->format('YmdH')));
            } elseif (! $fromEmployer) {
                $this->tellEmployer($application->listing, 'message', $params, "/work/employer/listings/{$application->listing_id}/applicants/{$application->id}", 'msg:'.$application->id.':'.now()->format('YmdH'));
            }
        }

        return ! $held;
    }

    /**
     * Daily: "no ghosting" outcomes, interview reminders, retention check-ins, "applying is open"
     * notices for saved jobs, weekly reminders to employers, and the 12-month anonymisation.
     *
     * @return array<string, int>
     */
    public function housekeeping(): array
    {
        $done = ['outcomes' => 0, 'reminders' => 0, 'retention' => 0, 'applying_open' => 0, 'waiting' => 0, 'anonymised' => 0];

        // No ghosting: 7 days after an advert closes, everyone still open hears a kind outcome.
        Application::query()->with(['listing.organisation', 'user'])->whereIn('stage', Application::OPEN)->whereNull('outcome_notified_at')
            ->whereHas('listing', fn ($q) => $q->whereIn('status', ['closed', 'filled', 'expired', 'taken_down'])->where('closed_at', '<=', now()->subDays(self::NO_GHOSTING_DAYS)))
            ->each(function (Application $a) use (&$done): void {
                $a->forceFill(['stage' => 'unsuccessful', 'stage_changed_at' => now(), 'outcome_notified_at' => now(), 'reject_reason' => 'position_filled'])->save();
                $this->event($a, 'stage', 'unsuccessful', null, ['automatic' => true]);
                if ($a->user !== null) {
                    $this->notifier->send($a->user, new ApplicantNotification('unsuccessful', ['title' => $a->listing->title, 'employer' => $a->listing->organisation->displayName(), 'message' => ''], '/work/applications/'.$a->id));
                }
                $done['outcomes']++;
            });

        // Interview reminders: the daily run reminds everyone with an interview in the next 36 hours, once.
        DB::table('work_interviews')->where('status', 'confirmed')->whereNull('reminded_at')->whereBetween('starts_at', [now()->addHours(2), now()->addHours(36)])
            ->get()->each(function (object $i) use (&$done): void {
                $a = Application::query()->with(['listing.organisation', 'user'])->whereKey($i->application_id)->first();
                if ($a?->user !== null) {
                    $this->notifier->send($a->user, new ApplicantNotification('interview_reminder', ['title' => $a->listing->title, 'employer' => $a->listing->organisation->displayName(),
                        'when' => SaFormat::dateTime(CarbonImmutable::parse((string) $i->starts_at))], '/work/applications/'.$a->id, 'iv-remind:'.$i->id));
                }
                DB::table('work_interviews')->where('id', $i->id)->update(['reminded_at' => now()]);
                $done['reminders']++;
            });

        // Retention check-ins at 30 and 90 days.
        DB::table('work_retention_checks')->whereNull('sent_at')->where('due_on', '<=', now('Africa/Johannesburg')->toDateString())->get()
            ->each(function (object $c) use (&$done): void {
                $a = Application::query()->with(['listing.organisation', 'user'])->whereKey($c->application_id)->first();
                if ($a?->user !== null) {
                    $this->notifier->send($a->user, new ApplicantNotification('retention', ['employer' => $a->listing->organisation->displayName(), 'days' => (string) $c->days],
                        '/work/applications/'.$a->id, 'retention:'.$c->id));
                }
                DB::table('work_retention_checks')->where('id', $c->id)->update(['sent_at' => now()]);
                $done['retention']++;
            });

        // "Applying is now open" for jobs people saved before applying existed (one message each).
        DB::table('work_saved_jobs')->join('work_listings', 'work_listings.id', '=', 'work_saved_jobs.listing_id')
            ->whereNull('work_saved_jobs.notified_at')->where('work_listings.status', 'live')
            ->get(['work_saved_jobs.user_id', 'work_saved_jobs.listing_id', 'work_listings.title'])->groupBy('user_id')
            ->each(function ($saved, $userId) use (&$done): void {
                $user = User::query()->find($userId);
                if ($user !== null) {
                    $this->notifier->send($user, new ApplicantNotification('applying_open', ['count' => (string) $saved->count(), 'title' => (string) ($saved->first()->title ?? '')], '/work/jobs?saved=1', 'applying-open'));
                    $done['applying_open']++;
                }
                DB::table('work_saved_jobs')->where('user_id', $userId)->whereIn('listing_id', $saved->pluck('listing_id'))->update(['notified_at' => now()]);
            });

        // Mondays: remind employers about applicants waiting more than 3 days.
        if (now('Africa/Johannesburg')->isMonday()) {
            Application::query()->with('listing.organisation')->where('stage', 'new')->where('created_at', '<=', now()->subDays(3))->get()->groupBy('listing_id')
                ->each(function ($apps) use (&$done): void {
                    $listing = $apps->first()?->listing;
                    if ($listing === null) {
                        return;
                    }
                    $this->tellEmployer($listing, 'waiting', ['count' => (string) $apps->count(), 'title' => $listing->title], "/work/employer/listings/{$listing->id}/applicants", 'waiting:'.$listing->id.':'.now()->format('oW'));
                    $done['waiting']++;
                });
        }

        // POPIA: 12 months after the advert closed, keep only anonymous counts.
        Application::query()->whereNull('anonymised_at')->whereHas('listing', fn ($q) => $q->where('closed_at', '<=', now()->subMonths(12)))
            ->each(function (Application $a) use (&$done): void {
                DB::table('work_messages')->where('application_id', $a->id)->delete();
                DB::table('work_application_notes')->where('application_id', $a->id)->delete();
                $a->forceFill(['user_id' => null, 'cv_id' => null, 'answers' => null, 'message' => null, 'anonymised_at' => now()])->save();
                $done['anonymised']++;
            });

        return $done;
    }

    /** @param array<string, string> $params */
    private function tellEmployer(JobListing $listing, string $kind, array $params, string $link, ?string $dedupe = null): void
    {
        $team = RoleAssignment::query()->whereIn('role', ['employer_admin', 'recruiter'])->where('scope_type', 'organisation')->where('scope_id', $listing->organisation_id)->pluck('user_id');
        User::query()->whereIn('id', $team)->get()->each(fn (User $u) => $this->notifier->send($u, new EmployerNotification($kind, $params, $link, $dedupe)));
    }

    /** @param array<string, mixed> $meta */
    private function event(Application $application, string $kind, ?string $stage, ?User $actor, array $meta = []): void
    {
        DB::table('work_application_events')->insert(['application_id' => $application->id, 'kind' => $kind, 'to_stage' => $stage,
            'actor_id' => $actor?->id, 'meta' => $meta === [] ? null : json_encode($meta), 'created_at' => now()]);
    }
}
