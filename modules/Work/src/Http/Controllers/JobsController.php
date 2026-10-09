<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use App\Support\Format\SaFormat;
use App\Support\Seo\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Work\Models\JobListing;
use Modules\Work\Services\Listings;

/**
 * The job board for young people (signed in, adults) and public job pages (shareable,
 * findable by search engines - never with employer contact details).
 */
final class JobsController extends WorkController
{
    public function index(Request $request): Response
    {
        [$user] = $this->who($request);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(JobListing::TYPES)],
            'sector' => ['nullable', 'string', 'max:32'],
            'distance' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'no_experience' => ['nullable', 'boolean'],
            'saved' => ['nullable', 'boolean'],
        ]);

        /** @var list<string> $saved */
        $saved = array_values(array_map('strval', DB::table('work_saved_jobs')->where('user_id', $user->id)->pluck('listing_id')->all()));
        $origin = $this->origin($user);

        $jobs = JobListing::query()->live()->with(['organisation', 'municipality'])
            ->when($filters['q'] ?? null, fn (Builder $q, string $text) => $q->where(fn (Builder $w) => $w->where('title', 'like', "%{$text}%")->orWhere('description', 'like', "%{$text}%")))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['sector'] ?? null, fn (Builder $q, string $sector) => $q->whereHas('organisation', fn (Builder $o) => $o->where('sector', $sector)))
            ->when($filters['no_experience'] ?? false, fn (Builder $q) => $q->where('experience', 'none'))
            ->when($filters['saved'] ?? false, fn (Builder $q) => $q->whereIn('id', $saved))
            ->latest('published_at')->limit(300)->get()
            ->map(fn (JobListing $l): array => $this->card($l, $origin, $saved))
            ->when(isset($filters['distance']) && $origin !== null, fn ($c) => $c->filter(fn (array $j): bool => $j['distanceKm'] !== null && $j['distanceKm'] <= (int) $filters['distance']))
            ->sortBy(fn (array $j): float => is_numeric($j['distanceKm']) ? (float) $j['distanceKm'] : 9999.0)->values();

        return Inertia::render('Work/Jobs/Index', [
            'filters' => $filters,
            'jobs' => $jobs->take(100)->values(),
            'hasLocation' => $origin !== null,
            'options' => ['types' => JobListing::TYPES, 'sectors' => EmployerController::SECTORS],
        ]);
    }

    public function show(Request $request, JobListing $listing): Response
    {
        [$user] = $this->who($request);
        abort_unless($listing->status === 'live' || $this->isStaff($user), 404);
        $this->countView($listing);

        return Inertia::render('Work/Jobs/Show', [
            'job' => $this->detail($listing),
            'saved' => DB::table('work_saved_jobs')->where('user_id', $user->id)->where('listing_id', $listing->id)->exists(),
            'canTakeDown' => $this->isStaff($user) && in_array($listing->status, ['live', 'review'], true),
        ]);
    }

    public function save(Request $request, JobListing $listing): RedirectResponse
    {
        [$user] = $this->who($request);
        abort_unless($listing->status === 'live', 404);
        DB::table('work_saved_jobs')->insertOrIgnore(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()]);

        return back()->with('status', __('work.jobs.saved'));
    }

    public function unsave(Request $request, JobListing $listing): RedirectResponse
    {
        [$user] = $this->who($request);
        DB::table('work_saved_jobs')->where('user_id', $user->id)->where('listing_id', $listing->id)->delete();

        return back()->with('status', __('work.jobs.unsaved'));
    }

    /** Public, shareable job page with Google JobPosting data. No employer contact details. */
    public function public(Request $request, JobListing $listing): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->isMinor()) {
            return to_route('work.jobs.show', $listing);
        }

        $open = $listing->status === 'live' && ! $listing->closes_on->isPast();
        if ($open) {
            $this->countView($listing);
        }

        $job = $this->detail($listing);
        $jsonLd = $open ? [[
            '@context' => 'https://schema.org', '@type' => 'JobPosting',
            'title' => $listing->title, 'description' => nl2br(e($listing->description)),
            'datePosted' => $listing->published_at?->toDateString(), 'validThrough' => $listing->closes_on->toDateString().'T23:59:59+02:00',
            'employmentType' => match ($listing->type) {
                'full_time' => 'FULL_TIME', 'part_time' => 'PART_TIME', 'temporary', 'piece_work' => 'TEMPORARY', default => 'INTERN'
            },
            'hiringOrganization' => ['@type' => 'Organization', 'name' => $listing->organisation->displayName()],
            'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $listing->place_name ?? $listing->municipality?->name, 'addressRegion' => $listing->municipality?->province?->name, 'addressCountry' => 'ZA']],
            'baseSalary' => ['@type' => 'MonetaryAmount', 'currency' => 'ZAR', 'value' => ['@type' => 'QuantitativeValue', 'minValue' => $listing->pay_min_cents / 100, 'maxValue' => ($listing->pay_max_cents ?? $listing->pay_min_cents) / 100, 'unitText' => strtoupper($listing->pay_period)]],
            'directApply' => true,
        ]] : [];

        return Inertia::render('Work/Jobs/Public', [
            'job' => $job,
            'open' => $open,
            'seo' => Seo::page($listing->title.' - '.$listing->organisation->displayName(), mb_strimwidth($listing->description, 0, 155, '...'), '/jobs/'.$listing->id, $jsonLd, noindex: ! $open),
        ]);
    }

    /** Staff take a live listing down (with a reason the employer sees). */
    public function takeDown(Request $request, JobListing $listing, Listings $listings): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->isStaff($user), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        abort_unless(in_array($listing->status, ['live', 'review'], true), 409);
        $listings->close($listing, 'taken_down', $user, $data['reason']);

        return back()->with('status', __('work.jobs.taken_down'));
    }

    private function isStaff(User $user): bool
    {
        return app(AccessResolver::class)->hasPermission($user, 'admin.moderation.review');
    }

    private function countView(JobListing $listing): void
    {
        $day = now('Africa/Johannesburg')->toDateString();
        DB::table('work_listing_views')->insertOrIgnore(['listing_id' => $listing->id, 'day' => $day, 'views' => 0]);
        DB::table('work_listing_views')->where('listing_id', $listing->id)->where('day', $day)->increment('views');
    }

    /** @return array{0: float, 1: float}|null The person's home hub (or area) position. */
    private function origin(User $user): ?array
    {
        $hub = $user->home_hub_id !== null ? Hub::query()->find($user->home_hub_id) : null;
        if ($hub?->latitude !== null && $hub->longitude !== null) {
            return [(float) $hub->latitude, (float) $hub->longitude];
        }

        $m = $user->municipality_id !== null ? Municipality::query()->find($user->municipality_id) : null;

        return $m?->latitude !== null && $m->longitude !== null ? [(float) $m->latitude, (float) $m->longitude] : null;
    }

    /**
     * @param  array{0: float, 1: float}|null  $origin
     * @param  list<string>  $saved
     * @return array<string, mixed>
     */
    private function card(JobListing $l, ?array $origin, array $saved): array
    {
        $distance = ($origin !== null && $l->latitude !== null && $l->longitude !== null)
            ? round($this->km($origin[0], $origin[1], (float) $l->latitude, (float) $l->longitude)) : null;

        return [
            'id' => $l->id, 'title' => $l->title, 'employer' => $l->organisation->displayName(), 'verified' => $l->organisation->isVerified(),
            'community' => $l->organisation->community, 'type' => $l->type, 'place' => $l->place_name ?? $l->municipality?->name,
            'pay' => $this->pay($l), 'noExperience' => $l->experience === 'none', 'closesOn' => $l->closes_on->toDateString(),
            'distanceKm' => $distance, 'saved' => in_array($l->id, $saved, true),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(JobListing $l): array
    {
        $l->loadMissing(['organisation', 'municipality.province', 'occupation', 'skills', 'questions']);

        return [
            'id' => $l->id, 'title' => $l->title, 'status' => $l->status, 'type' => $l->type, 'positions' => $l->positions,
            'employer' => ['name' => $l->organisation->displayName(), 'verified' => $l->organisation->isVerified(), 'community' => $l->organisation->community,
                'description' => $l->organisation->description, 'sector' => $l->organisation->sector],
            'occupation' => $l->occupation?->title, 'place' => $l->place_name, 'area' => $l->municipality?->name, 'province' => $l->municipality?->province?->name,
            'latitude' => $l->latitude !== null ? (float) $l->latitude : null, 'longitude' => $l->longitude !== null ? (float) $l->longitude : null,
            'pay' => $this->pay($l), 'hours' => $l->hours, 'education' => $l->education, 'licence' => $l->licence, 'experience' => $l->experience,
            'languages' => $l->languages ?? [], 'description' => $l->description, 'closesOn' => $l->closes_on->toDateString(),
            'postedOn' => $l->published_at?->toDateString(),
            'mustSkills' => $l->skills->where('must', true)->pluck('name')->values(), 'niceSkills' => $l->skills->where('must', false)->pluck('name')->values(),
            'questions' => $l->questions->pluck('question')->values(),
        ];
    }

    private function pay(JobListing $l): string
    {
        $amount = SaFormat::money($l->pay_min_cents).($l->pay_max_cents !== null && $l->pay_max_cents > $l->pay_min_cents ? ' - '.SaFormat::money($l->pay_max_cents) : '');

        return $amount.' '.__('work.pay.per_'.$l->pay_period);
    }

    private function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
