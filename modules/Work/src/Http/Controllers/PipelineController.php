<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Work\Models\Application;
use Modules\Work\Models\JobListing;
use Modules\Work\Services\CurrentEmployer;
use Modules\Work\Services\Hiring;
use Modules\Work\Services\WorkSubject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The employer's hiring pipeline for an advert: stages, bulk moves, team notes, interviews,
 * messages and the applicant's CV (audited). Blind shortlisting hides names until "Interview".
 */
final class PipelineController extends WorkController
{
    public function __construct(WorkSubject $subject, private readonly CurrentEmployer $employers)
    {
        parent::__construct($subject);
    }

    public function index(Request $request, JobListing $listing): Response
    {
        $this->authorise($request, $listing);
        $applications = Application::query()->with(['user', 'listing'])->where('listing_id', $listing->id)->where('stage', '!=', 'withdrawn')
            ->orderByDesc('score')->orderBy('created_at')->get();

        return Inertia::render('Work/Employer/Pipeline', [
            'listing' => ['id' => $listing->id, 'title' => $listing->title, 'status' => $listing->status, 'blind' => $listing->blind_shortlisting],
            'stages' => Application::STAGES,
            'reasons' => Application::REJECT_REASONS,
            'applications' => $applications->map(fn (Application $a): array => $this->card($a))->values(),
        ]);
    }

    public function show(Request $request, JobListing $listing, Application $application, AuditLogger $audit): Response
    {
        [$user] = $this->authorise($request, $listing);
        abort_unless($application->listing_id === $listing->id && $application->stage !== 'withdrawn', 404);
        $application->load(['user', 'listing']);
        $audit->record('work.application_viewed', $application->user, meta: ['application' => $application->id], actor: $user);
        DB::table('work_messages')->where('application_id', $application->id)->where('from_employer', false)->whereNull('read_at')->update(['read_at' => now()]);
        $blind = $application->blind();

        return Inertia::render('Work/Employer/Applicant', [
            'listing' => ['id' => $listing->id, 'title' => $listing->title],
            'application' => [
                ...$this->card($application),
                'answers' => $application->answers ?? [], 'message' => $application->message,
                'cvUrl' => ! $blind && $application->cv_id !== null && $application->anonymised_at === null ? "/work/employer/listings/{$listing->id}/applicants/{$application->id}/cv" : null,
                'reasons' => MatchesController::explain((string) (DB::table('work_matches')->where('listing_id', $listing->id)->where('user_id', $application->user_id)->value('reasons') ?? '[]')),
                'hireConfirmed' => $application->hire_confirmed_at !== null,
            ],
            'stages' => Application::STAGES,
            'reasons' => Application::REJECT_REASONS,
            'timeline' => ApplicationsController::timeline($application, true),
            'interviews' => ApplicationsController::interviews($application),
            'messages' => ApplicationsController::messages($application, $user->id),
            'notes' => DB::table('work_application_notes')->leftJoin('users', 'users.id', '=', 'work_application_notes.author_id')
                ->where('application_id', $application->id)->orderBy('work_application_notes.created_at')
                ->get(['work_application_notes.body', 'work_application_notes.created_at', 'users.first_name'])
                ->map(static fn (object $n): array => ['body' => $n->body, 'author' => $n->first_name, 'at' => (string) $n->created_at]),
        ]);
    }

    public function move(Request $request, JobListing $listing, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->authorise($request, $listing);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['string'],
            'stage' => ['required', Rule::in(Application::STAGES)],
            'message' => ['nullable', 'string', 'max:500'],
            'reason' => ['nullable', Rule::in(Application::REJECT_REASONS)],
        ]);

        $moved = 0;
        foreach (Application::query()->with(['listing.organisation', 'user'])->where('listing_id', $listing->id)->whereIn('id', $data['ids'])->get() as $application) {
            try {
                $hiring->move($application, $data['stage'], $user, $data['message'] ?? null, $data['reason'] ?? null);
                $moved++;
            } catch (DomainException) {
                // withdrawn or hired applications stay where they are
            }
        }

        return back()->with('status', __('work.pipeline.moved', ['count' => $moved]));
    }

    public function note(Request $request, JobListing $listing, Application $application, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->authorise($request, $listing);
        abort_unless($application->listing_id === $listing->id, 404);
        $hiring->note($application, $user, (string) $request->validate(['body' => ['required', 'string', 'max:2000']])['body']);

        return back()->with('status', __('work.pipeline.note_added'));
    }

    public function interview(Request $request, JobListing $listing, Application $application, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->authorise($request, $listing);
        abort_unless($application->listing_id === $listing->id && in_array($application->stage, Application::OPEN, true), 404);
        $data = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'mode' => ['required', Rule::in(['in_person', 'hub', 'phone', 'video'])],
            'place' => ['nullable', 'string', 'max:200', 'required_if:mode,in_person,hub'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $application->load(['listing.organisation', 'user']);
        $hiring->proposeInterview($application, $user, $data['starts_at'], $data['mode'], $data['place'] ?? null, $data['note'] ?? null);

        return back()->with('status', __('work.interview.proposed'));
    }

    public function message(Request $request, JobListing $listing, Application $application, Hiring $hiring): RedirectResponse
    {
        [$user] = $this->authorise($request, $listing);
        abort_unless($application->listing_id === $listing->id && $application->anonymised_at === null, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $application->load(['listing.organisation', 'user']);

        return back()->with('status', __($hiring->message($application, $user, true, $data['body']) ? 'work.messages.sent' : 'work.messages.held'));
    }

    /** The applicant's CV, for this employer's team only (audited; hidden while blind). */
    public function cv(Request $request, JobListing $listing, Application $application, DocumentIssuer $issuer, AuditLogger $audit): StreamedResponse
    {
        [$user] = $this->authorise($request, $listing);
        abort_unless($application->listing_id === $listing->id && ! $application->blind() && $application->cv?->document !== null && $application->anonymised_at === null, 404);
        $audit->record('work.application_cv_viewed', $application->user, meta: ['application' => $application->id], actor: $user);

        return $issuer->download($application->cv->document, 'inline');
    }

    /** @return array{0: User} */
    private function authorise(Request $request, JobListing $listing): array
    {
        [$user] = $this->who($request);
        abort_unless($this->employers->all($user)->contains('id', $listing->organisation_id), 404);

        return [$user];
    }

    /** @return array<string, mixed> */
    private function card(Application $a): array
    {
        $blind = $a->blind();
        $person = $a->user;

        return [
            'id' => $a->id, 'stage' => $a->stage, 'score' => $a->score, 'reference' => $a->reference, 'blind' => $blind,
            'name' => $person === null ? __('work.pipeline.removed') : ($blind ? __('work.pipeline.applicant', ['ref' => $a->reference]) : $person->fullName()),
            'phone' => $person !== null && ! $blind ? SaFormat::phone($person->phone) : null,
            'headline' => $person !== null ? DB::table('work_profiles')->where('user_id', $person->id)->value('headline') : null,
            'appliedAt' => $a->created_at->toIso8601String(), 'unread' => DB::table('work_messages')->where('application_id', $a->id)->where('from_employer', false)->whereNull('read_at')->where('held', false)->count(),
        ];
    }
}
