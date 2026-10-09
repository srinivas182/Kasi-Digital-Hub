<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Identity\Models\User;
use Modules\Work\Models\Application;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\WorkCv;
use Modules\Work\Services\CurrentEmployer;
use Modules\Work\Services\Hiring;

/**
 * Young people: apply, follow applications, answer interviews, message employers, confirm a hire
 * and answer retention check-ins.
 */
final class ApplicationsController extends WorkController
{
    public function create(Request $request, JobListing $listing): Response|RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $existing = Application::query()->where('listing_id', $listing->id)->where('user_id', $user->id)->value('id');
        if ($existing !== null) {
            return to_route('work.applications.show', $existing);
        }
        abort_unless($listing->status === 'live', 404);
        $listing->load('organisation', 'questions');

        return Inertia::render('Work/Applications/Apply', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'job' => ['id' => $listing->id, 'title' => $listing->title, 'employer' => $listing->organisation->displayName()],
            'questions' => $listing->questions->map(static fn ($q): array => ['question' => $q->question, 'kind' => $q->kind])->values(),
            'cvs' => WorkCv::query()->where('user_id', $user->id)->latest('created_at')->limit(5)->get()
                ->map(static fn (WorkCv $cv): array => ['id' => $cv->id, 'template' => $cv->template, 'createdAt' => $cv->created_at->toIso8601String()]),
            'hasProfile' => DB::table('work_profiles')->where('user_id', $user->id)->whereNotNull('headline')->exists(),
            'gaps' => MatchesController::explain((string) (DB::table('work_matches')->where('listing_id', $listing->id)->where('user_id', $user->id)->value('gaps') ?? '[]')),
        ]);
    }

    public function store(Request $request, JobListing $listing, Hiring $hiring): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate([
            'cv_id' => ['nullable', 'string', Rule::exists('work_cvs', 'id')->where('user_id', $user->id)],
            'answers' => ['array', 'max:5'], 'answers.*' => ['nullable', 'string', 'max:300'],
            'message' => ['nullable', 'string', 'max:500'],
            'share' => ['accepted'],
        ]);

        try {
            $application = $hiring->apply($listing, $user, $data['cv_id'] ?? null, array_values($data['answers'] ?? []), $data['message'] ?? null, $by);
        } catch (DomainException $e) {
            return back()->withErrors(['apply' => $e->getMessage()]);
        }

        return to_route('work.applications.show', $application)->with('status', __('work.apply.sent'));
    }

    public function index(Request $request): Response
    {
        [$user] = $this->who($request);

        return Inertia::render('Work/Applications/Index', [
            'applications' => Application::query()->with('listing.organisation')->where('user_id', $user->id)->latest('created_at')->limit(50)->get()
                ->map(static fn (Application $a): array => [
                    'id' => $a->id, 'title' => $a->listing->title, 'employer' => $a->listing->organisation->displayName(), 'stage' => $a->stage,
                    'appliedAt' => $a->created_at->toIso8601String(), 'changedAt' => $a->stage_changed_at?->toIso8601String(),
                ]),
        ]);
    }

    public function show(Request $request, Application $application): Response
    {
        [$user, $by] = $this->who($request);
        abort_unless($application->user_id === $user->id, 404);
        $application->load('listing.organisation');
        DB::table('work_messages')->where('application_id', $application->id)->where('from_employer', true)->whereNull('read_at')->update(['read_at' => now()]);

        return Inertia::render('Work/Applications/Show', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'application' => [
                'id' => $application->id, 'stage' => $application->stage, 'title' => $application->listing->title, 'listingId' => $application->listing_id,
                'employer' => $application->listing->organisation->displayName(), 'appliedAt' => $application->created_at->toIso8601String(),
                'answers' => $application->answers ?? [], 'message' => $application->message,
                'hired' => $application->stage === 'hired', 'hireConfirmed' => $application->hire_confirmed_at !== null, 'hiredOn' => $application->hired_on?->toDateString(),
            ],
            'timeline' => self::timeline($application, false),
            'interviews' => self::interviews($application),
            'messages' => self::messages($application, $user->id),
            'retention' => DB::table('work_retention_checks')->where('application_id', $application->id)->whereNotNull('sent_at')->whereNull('answered_at')
                ->get(['id', 'days'])->map(static fn (object $c): array => ['id' => (int) $c->id, 'days' => (int) $c->days]),
        ]);
    }

    public function withdraw(Request $request, Application $application, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->who($request);
        abort_unless($application->user_id === $user->id, 404);

        try {
            $hiring->withdraw($application, $user);
        } catch (DomainException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('status', __('work.apply.withdrawn'));
    }

    public function answerInterview(Request $request, string $interview, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->who($request);
        $data = $request->validate(['answer' => ['required', Rule::in(['confirmed', 'reschedule_requested', 'declined'])]]);

        try {
            $hiring->answerInterview($interview, $user, $data['answer']);
        } catch (DomainException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('status', __('work.interview.answered.'.$data['answer']));
    }

    public function message(Request $request, Application $application, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->who($request);
        abort_unless($application->user_id === $user->id && $application->anonymised_at === null, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return back()->with('status', __($hiring->message($application, $user, false, $data['body']) ? 'work.messages.sent' : 'work.messages.held'));
    }

    public function confirmHire(Request $request, Application $application, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->who($request);
        abort_unless($application->user_id === $user->id, 404);
        $data = $request->validate(['started' => ['required', 'boolean'], 'started_on' => ['nullable', 'date', 'before_or_equal:today']]);

        try {
            $hiring->confirmHire($application, $user, (bool) $data['started'], $data['started_on'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('status', __($data['started'] ? 'work.hire.confirmed' : 'work.hire.not_started'));
    }

    public function retention(Request $request, int $check, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->who($request);
        $data = $request->validate(['still_working' => ['required', 'boolean']]);

        try {
            $hiring->answerRetention($check, $user, (bool) $data['still_working']);
        } catch (DomainException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('status', __('work.hire.retention_thanks'));
    }

    /** Reporting a message sends it to the content review queue. */
    public function report(Request $request, string $message): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $row = DB::table('work_messages')->join('work_applications', 'work_applications.id', '=', 'work_messages.application_id')
            ->where('work_messages.id', $message)->first(['work_messages.*', 'work_applications.user_id as applicant', 'work_applications.listing_id']);
        abort_if($row === null, 404);
        $listing = JobListing::query()->whereKey($row->listing_id)->firstOrFail();
        $isApplicant = $row->applicant === $user->id;
        $isEmployer = app(CurrentEmployer::class)->all($user)->contains('id', $listing->organisation_id);
        abort_unless($isApplicant || $isEmployer, 404);

        DB::table('work_messages')->where('id', $message)->update(['reported_at' => now()]);
        ModerationFlag::query()->updateOrCreate(['subject_type' => 'work_message', 'subject_id' => $message, 'status' => 'pending'],
            ['author_id' => $row->sender_id, 'excerpt' => mb_substr((string) $row->body, 0, 1000), 'reasons' => ['Reported by '.($isApplicant ? 'the applicant' : 'the employer')], 'source' => 'rules']);

        return back()->with('status', __('work.messages.reported'));
    }

    /** @return list<array{kind: string, stage: string|null, at: string, meta: array<mixed>}> */
    public static function timeline(Application $a, bool $forEmployer): array
    {
        return array_values(DB::table('work_application_events')->where('application_id', $a->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(static function (object $e) use ($forEmployer): array {
                $meta = (array) json_decode((string) ($e->meta ?? '{}'), true);
                if (! $forEmployer) {
                    unset($meta['reason']);
                }

                return ['kind' => (string) $e->kind, 'stage' => $e->to_stage !== null ? (string) $e->to_stage : null, 'at' => (string) $e->created_at, 'meta' => $meta];
            })->all());
    }

    /** @return list<array<string, mixed>> */
    public static function interviews(Application $a): array
    {
        return array_values(DB::table('work_interviews')->where('application_id', $a->id)->orderByDesc('created_at')->get()
            ->map(static fn (object $i): array => ['id' => $i->id, 'startsAt' => CarbonImmutable::parse((string) $i->starts_at)->toIso8601String(), 'mode' => $i->mode, 'place' => $i->place,
                'note' => $i->note, 'status' => $i->status])->all());
    }

    /** @return list<array<string, mixed>> */
    public static function messages(Application $a, string $viewerId): array
    {
        return array_values(DB::table('work_messages')->where('application_id', $a->id)->orderBy('created_at')->orderBy('id')->get()
            ->filter(static fn (object $m): bool => ! $m->held || $m->sender_id === $viewerId)
            ->map(static fn (object $m): array => ['id' => $m->id, 'fromEmployer' => (bool) $m->from_employer, 'body' => $m->body, 'held' => (bool) $m->held,
                'mine' => $m->sender_id === $viewerId, 'at' => (string) $m->created_at])->all());
    }
}
