<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\AiBudget;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Work\Http\Controllers\ProfileController as Profile;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\OfoOccupation;
use Modules\Work\Services\CurrentEmployer;
use Modules\Work\Services\ListingChecks;
use Modules\Work\Services\Listings;
use Modules\Work\Services\ListingWriter;
use Modules\Work\Services\WorkSubject;

/**
 * Employers create and manage job listings.
 */
final class ListingController extends WorkController
{
    public function __construct(WorkSubject $subject, private readonly CurrentEmployer $employers)
    {
        parent::__construct($subject);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, JobListing $listing): Response
    {
        $this->own($request, $listing);

        return $this->form($request, $listing);
    }

    public function store(Request $request, Listings $listings, ListingChecks $checks): RedirectResponse
    {
        [$user, $by, $employer] = $this->employer($request);
        [$data, $skills, $questions] = $this->validated($request, $checks);
        $listing = $listings->save($employer, $data, $skills, $questions, $by ?? $user);

        return $request->boolean('publish') ? $this->publish($request, $listing, $listings) : to_route('work.employer.listings.edit', $listing)->with('status', __('work.listing.saved_draft'));
    }

    public function update(Request $request, JobListing $listing, Listings $listings, ListingChecks $checks): RedirectResponse
    {
        [$user, $by, $employer] = $this->employer($request);
        $this->own($request, $listing);
        abort_if(in_array($listing->status, ['taken_down'], true), 409);
        [$data, $skills, $questions] = $this->validated($request, $checks);

        try {
            $listings->save($employer, $data, $skills, $questions, $by ?? $user, $listing);
        } catch (DomainException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return $request->boolean('publish') && $listing->status === 'draft' ? $this->publish($request, $listing, $listings) : back()->with('status', __('work.saved'));
    }

    public function publish(Request $request, JobListing $listing, Listings $listings): RedirectResponse
    {
        [$user, $by] = $this->employer($request);
        $this->own($request, $listing);

        try {
            $listings->publish($listing, $by ?? $user);
        } catch (DomainException $e) {
            return to_route('work.employer.listings.edit', $listing)->withErrors(['publish' => $e->getMessage()]);
        }

        return to_route('work.employer')->with('status', __($listing->status === 'live' ? 'work.listing.live' : 'work.listing.in_review'));
    }

    public function close(Request $request, JobListing $listing, Listings $listings): RedirectResponse
    {
        [$user, $by] = $this->employer($request);
        $this->own($request, $listing);
        $data = $request->validate(['status' => ['required', Rule::in(['closed', 'filled'])]]);
        abort_unless(in_array($listing->status, ['live', 'review', 'draft'], true), 409);
        $listings->close($listing, $data['status'], $by ?? $user);

        return back()->with('status', __('work.listing.closed'));
    }

    public function renew(Request $request, JobListing $listing, Listings $listings): RedirectResponse
    {
        [$user, $by] = $this->employer($request);
        $this->own($request, $listing);
        abort_unless(in_array($listing->status, ['closed', 'expired'], true), 409);
        $data = $request->validate(['closes_on' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays((int) config('kasi.work.max_listing_days'))->toDateString()]]);
        $listing->forceFill(['closes_on' => $data['closes_on'], 'status' => 'draft', 'closed_at' => null, 'reminded_at' => null])->save();

        return $this->publish($request, $listing, $listings);
    }

    /** "Help me write it": rough notes to suggested fields. */
    public function write(Request $request, ListingWriter $writer): JsonResponse
    {
        [$user, $by] = $this->employer($request);
        $data = $request->validate(['notes' => ['required', 'string', 'min:10', 'max:1500']]);
        $result = $writer->suggest($data['notes'], $by ?? $user);

        return response()->json($result['ok'] ? $result : ['ok' => false, 'message' => __('ai.fallback.'.($result['reason'] ?? 'unavailable'))]);
    }

    public function occupations(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(OfoOccupation::query()->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.$q.'%'))
            ->orderBy('title')->limit(15)->get(['id', 'title', 'code']));
    }

    private function form(Request $request, ?JobListing $listing): Response
    {
        [$user, $by, $employer] = $this->employer($request);
        $listing?->load(['skills', 'questions', 'occupation']);

        return Inertia::render('Work/Employer/ListingEdit', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'employer' => ['name' => $employer->displayName(), 'verified' => $employer->isVerified()],
            'listing' => $listing === null ? null : [
                'id' => $listing->id, 'status' => $listing->status, 'reason' => $listing->status_reason,
                'title' => $listing->title, 'occupation' => $listing->occupation ? ['id' => $listing->occupation->id, 'title' => $listing->occupation->title] : null,
                'type' => $listing->type, 'positions' => $listing->positions, 'municipalityId' => $listing->municipality_id, 'placeName' => $listing->place_name,
                'payMin' => $listing->pay_min_cents / 100, 'payMax' => $listing->pay_max_cents !== null ? $listing->pay_max_cents / 100 : null, 'payPeriod' => $listing->pay_period,
                'hours' => $listing->hours, 'education' => $listing->education, 'licence' => $listing->licence, 'experience' => $listing->experience,
                'languages' => $listing->languages ?? [], 'description' => $listing->description, 'closesOn' => $listing->closes_on->toDateString(),
                'skills' => $listing->skills->map(static fn ($s): array => ['name' => $s->name, 'must' => $s->must])->values(),
                'questions' => $listing->questions->map(static fn ($q): array => ['question' => $q->question, 'kind' => $q->kind])->values(),
            ],
            'cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
            'defaultCity' => $employer->municipality_id,
            'options' => [
                'types' => JobListing::TYPES, 'periods' => JobListing::PERIODS, 'education' => JobListing::EDUCATION, 'experience' => JobListing::EXPERIENCE,
                'licences' => Profile::LICENCES, 'languages' => Profile::LANGUAGES, 'maxDays' => (int) config('kasi.work.max_listing_days'),
                'minimumWage' => (int) config('kasi.work.minimum_wage_cents_per_hour') / 100,
            ],
            'aiEnabled' => app(AiBudget::class)->enabled('work.listing_writer'),
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: list<array{name: string, must: bool}>, 2: list<array{question: string, kind: string}>} */
    private function validated(Request $request, ListingChecks $checks): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'occupation_id' => ['nullable', 'integer', 'exists:ofo_occupations,id'],
            'type' => ['required', Rule::in(JobListing::TYPES)],
            'positions' => ['required', 'integer', 'min:1', 'max:500'],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->whereNot('category', 'district')],
            'place_name' => ['nullable', 'string', 'max:120'],
            'pay_min' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'pay_max' => ['nullable', 'numeric', 'gte:pay_min', 'max:1000000'],
            'pay_period' => ['required', Rule::in(JobListing::PERIODS)],
            'hours' => ['nullable', 'string', 'max:120'],
            'education' => ['nullable', Rule::in(JobListing::EDUCATION)],
            'licence' => ['nullable', Rule::in(Profile::LICENCES)],
            'experience' => ['required', Rule::in(JobListing::EXPERIENCE)],
            'languages' => ['array', 'max:6'], 'languages.*' => [Rule::in(Profile::LANGUAGES)],
            'description' => ['required', 'string', 'min:30', 'max:4000'],
            'closes_on' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays((int) config('kasi.work.max_listing_days'))->toDateString()],
            'skills' => ['array', 'max:12'], 'skills.*.name' => ['required', 'string', 'max:60'], 'skills.*.must' => ['boolean'],
            'questions' => ['array', 'max:5'], 'questions.*.question' => ['required', 'string', 'max:200'], 'questions.*.kind' => ['required', Rule::in(['yes_no', 'short'])],
        ]);

        $errors = [];
        if (($problem = $checks->payProblem($data['type'], $data['pay_period'], (int) round(((float) $data['pay_min']) * 100))) !== null) {
            $errors['pay_min'] = $problem;
        }
        foreach ($data['questions'] ?? [] as $i => $q) {
            if (($problem = $checks->questionProblem($q['question'])) !== null) {
                $errors["questions.{$i}.question"] = $problem;
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $place = $data['place_name'] ?? null;
        $municipality = Municipality::query()->whereKey($data['municipality_id'])->first();

        /** @var list<array{name: string, must: bool}> $skills */
        $skills = array_values(array_map(static fn (array $s): array => ['name' => (string) $s['name'], 'must' => (bool) ($s['must'] ?? true)], $data['skills'] ?? []));
        /** @var list<array{question: string, kind: string}> $questions */
        $questions = array_values(array_map(static fn (array $q): array => ['question' => (string) $q['question'], 'kind' => (string) $q['kind']], $data['questions'] ?? []));

        return [[
            'title' => $data['title'], 'occupation_id' => $data['occupation_id'] ?? null, 'type' => $data['type'], 'positions' => $data['positions'],
            'municipality_id' => $data['municipality_id'], 'place_name' => $place,
            'latitude' => $municipality?->latitude, 'longitude' => $municipality?->longitude,
            'pay_min_cents' => (int) round(((float) $data['pay_min']) * 100), 'pay_max_cents' => isset($data['pay_max']) ? (int) round(((float) $data['pay_max']) * 100) : null,
            'pay_period' => $data['pay_period'], 'hours' => $data['hours'] ?? null, 'education' => $data['education'] ?? null, 'licence' => $data['licence'] ?? null,
            'experience' => $data['experience'], 'languages' => $data['languages'] ?? [], 'description' => $data['description'], 'closes_on' => $data['closes_on'],
        ], $skills, $questions];
    }

    /** @return array{0: User, 1: User|null, 2: Organisation} */
    private function employer(Request $request): array
    {
        [$user, $by] = $this->who($request);
        $employer = $this->employers->resolve($request, $user);
        abort_if($employer === null, 403);

        return [$user, $by, $employer];
    }

    private function own(Request $request, JobListing $listing): void
    {
        [$user] = $this->who($request);
        abort_unless($this->employers->all($user)->contains('id', $listing->organisation_id), 404);
    }
}
