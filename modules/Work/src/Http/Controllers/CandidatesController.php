<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Work\Matching\Invitations;
use Modules\Work\Matching\Visibility;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Models\WorkProfile;
use Modules\Work\Services\CurrentEmployer;
use Modules\Work\Services\WorkSubject;

/**
 * Suggested candidates for a live advert. Only people with job matching switched on, not hidden
 * from this employer and without gaps. Anonymised (first name, surname initial, town) until they
 * accept an invitation or apply. Every profile view is recorded and shown to the person.
 */
final class CandidatesController extends WorkController
{
    public function __construct(WorkSubject $subject, private readonly CurrentEmployer $employers, private readonly Visibility $visibility)
    {
        parent::__construct($subject);
    }

    public function index(Request $request, JobListing $listing): Response
    {
        $this->authorise($request, $listing);
        $hidden = $this->visibility->hiddenFrom($listing->organisation_id);

        $rows = DB::table('work_matches')->where('listing_id', $listing->id)->where('gaps', '[]')
            ->whereNotIn('user_id', $hidden)->orderByDesc('score')->limit(200)->get(['user_id', 'score', 'reasons', 'distance_km']);
        $allowed = array_flip($this->visibility->consenting(array_values(array_map('strval', $rows->pluck('user_id')->all()))));
        $rows = $rows->filter(static fn (object $r): bool => isset($allowed[(string) $r->user_id]))->take(50);

        $invites = DB::table('work_invitations')->where('listing_id', $listing->id)->pluck('status', 'user_id');
        $people = User::query()->whereIn('id', $rows->pluck('user_id'))->get(['id', 'first_name', 'last_name', 'place_name', 'phone'])->keyBy('id');
        $profiles = WorkProfile::query()->whereIn('user_id', $rows->pluck('user_id'))->get(['user_id', 'headline'])->keyBy('user_id');

        return Inertia::render('Work/Employer/Candidates', [
            'listing' => ['id' => $listing->id, 'title' => $listing->title, 'status' => $listing->status],
            'invitesToday' => DB::table('work_invitations')->where('listing_id', $listing->id)->where('created_at', '>=', now()->subDay())->count(),
            'inviteLimit' => Invitations::DAILY_LIMIT,
            'candidates' => $rows->values()->map(function (object $r) use ($people, $profiles, $invites): array {
                $person = $people[$r->user_id] ?? null;
                $status = $invites[$r->user_id] ?? null;

                return [
                    'id' => (string) $r->user_id,
                    'name' => $person ? self::anonymous($person, $status === 'accepted') : '',
                    'phone' => $status === 'accepted' && $person ? SaFormat::phone($person->phone) : null,
                    'place' => $person?->place_name,
                    'headline' => $profiles[$r->user_id]->headline ?? null,
                    'score' => (int) $r->score,
                    'distanceKm' => $r->distance_km !== null ? (int) $r->distance_km : null,
                    'reasons' => MatchesController::explain((string) $r->reasons),
                    'invitation' => $status,
                ];
            }),
        ]);
    }

    public function show(Request $request, JobListing $listing, User $person, AuditLogger $audit): Response
    {
        [$viewer] = $this->authorise($request, $listing);
        $match = DB::table('work_matches')->where('listing_id', $listing->id)->where('user_id', $person->id)->first();
        abort_unless($match !== null && $this->visibility->canSee($listing->organisation_id, $person->id), 404);

        DB::table('work_profile_views')->insert(['user_id' => $person->id, 'organisation_id' => $listing->organisation_id, 'viewed_by' => $viewer->id, 'listing_id' => $listing->id, 'viewed_at' => now()]);
        $audit->record('work.profile_viewed', $person, meta: ['organisation' => $listing->organisation_id, 'listing' => $listing->id], actor: $viewer);

        $accepted = DB::table('work_invitations')->where('listing_id', $listing->id)->where('user_id', $person->id)->value('status');
        $verified = Document::query()->where('user_id', $person->id)->where('status', Document::VERIFIED)->pluck('id')->all();
        $profile = WorkProfile::query()->find($person->id);

        return Inertia::render('Work/Employer/Candidate', [
            'listing' => ['id' => $listing->id, 'title' => $listing->title],
            'candidate' => [
                'id' => $person->id,
                'name' => self::anonymous($person, $accepted === 'accepted'),
                'phone' => $accepted === 'accepted' ? SaFormat::phone($person->phone) : null,
                'place' => $person->place_name,
                'headline' => $profile?->headline,
                'summary' => $profile?->summary,
                'score' => (int) $match->score,
                'reasons' => MatchesController::explain((string) $match->reasons),
                'experience' => WorkExperience::query()->where('user_id', $person->id)->orderBy('position')->get()
                    ->map(static fn (WorkExperience $e): array => ['title' => $e->title, 'kind' => $e->kind, 'organisation' => $e->organisation, 'bullets' => $e->bullets ?: array_filter([$e->description])]),
                'education' => WorkEducation::query()->where('user_id', $person->id)->orderByDesc('year')->get()
                    ->map(static fn (WorkEducation $e): array => ['name' => $e->name, 'year' => $e->year, 'inProgress' => $e->in_progress, 'verified' => $e->document_id !== null && in_array($e->document_id, $verified, true)]),
                'skills' => DB::table('work_skills')->where('user_id', $person->id)->pluck('name'),
                'languages' => DB::table('work_languages')->where('user_id', $person->id)->get(['language', 'level']),
                'invitation' => $accepted,
            ],
        ]);
    }

    public function invite(Request $request, JobListing $listing, User $person, Invitations $invitations): RedirectResponse
    {
        [$viewer] = $this->authorise($request, $listing);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:300']]);

        try {
            $invitations->invite($listing, $person, $viewer, $data['message'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['invite' => $e->getMessage()]);
        }

        return back()->with('status', __('work.invite.sent'));
    }

    /** @return array{0: User} */
    private function authorise(Request $request, JobListing $listing): array
    {
        [$user] = $this->who($request);
        abort_unless($this->employers->all($user)->contains('id', $listing->organisation_id), 404);
        abort_unless($listing->organisation->isVerified(), 403);

        return [$user];
    }

    private static function anonymous(User $person, bool $revealed): string
    {
        return $revealed ? $person->fullName() : $person->first_name.' '.mb_substr((string) $person->last_name, 0, 1).'.';
    }
}
