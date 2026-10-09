<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Documents\Models\Document;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Models\WorkProfile;

/**
 * Builds matcher facts from the database, reading only the columns the matcher may use.
 */
final class MatchFacts
{
    public const EDUCATION_RANK = ['none' => 0, 'grade_9' => 1, 'matric' => 2, 'certificate' => 3, 'diploma' => 4, 'degree' => 5];

    /** @var array<string, string>|null */
    private ?array $synonyms = null;

    public function seeker(string $userId): ?SeekerFacts
    {
        $profile = WorkProfile::query()->find($userId);
        if ($profile === null) {
            return null;
        }

        // Location only: home hub (or area). Nothing else from the user record.
        $place = DB::table('users')->where('id', $userId)->first(['home_hub_id', 'municipality_id']);
        $origin = $this->origin($place?->home_hub_id, $place?->municipality_id);

        $months = 0;
        $experience = WorkExperience::query()->where('user_id', $userId)->get(['started', 'ended', 'duration']);
        foreach ($experience as $e) {
            $months += self::months($e->started, $e->ended, $e->duration);
        }

        $rank = 0;
        $verified = false;
        $verifiedDocs = Document::query()->where('user_id', $userId)->where('status', Document::VERIFIED)->pluck('id')->all();
        foreach (WorkEducation::query()->where('user_id', $userId)->where('in_progress', false)->get(['kind', 'name', 'document_id']) as $edu) {
            $r = match ($edu->kind) {
                'school' => preg_match('/(grade|gr\.?)\s*(9|10|11)/i', $edu->name) === 1 ? 1 : 0,
                'matric', 'learnership', 'short_course' => 2,
                'certificate' => 3, 'diploma' => 4, 'degree' => 5, default => 0,
            };
            if ($r > $rank || ($r === $rank && ! $verified)) {
                $verified = $edu->document_id !== null && in_array($edu->document_id, $verifiedDocs, true);
            }
            $rank = max($rank, $r);
        }

        return new SeekerFacts(
            userId: $userId,
            skills: $this->canonical(DB::table('work_skills')->where('user_id', $userId)->pluck('name')->all()),
            experienceMonths: $months,
            hasExperience: $experience->isNotEmpty(),
            educationRank: $rank,
            educationVerified: $verified,
            licence: $profile->drivers_licence !== 'none' ? $profile->drivers_licence : null,
            languages: array_values(array_map('strval', DB::table('work_languages')->where('user_id', $userId)->pluck('language')->all())),
            workTypes: $profile->work_types ?? [],
            sectors: $profile->sectors ?? [],
            maxTravelKm: $profile->max_travel_km,
            origin: $origin,
        );
    }

    public function listing(JobListing $listing): ListingFacts
    {
        $listing->loadMissing('skills', 'organisation');

        return new ListingFacts(
            listingId: $listing->id,
            mustSkills: $this->canonical($listing->skills->where('must', true)->pluck('name')->all()),
            niceSkills: $this->canonical($listing->skills->where('must', false)->pluck('name')->all()),
            experience: $listing->experience,
            educationRank: self::EDUCATION_RANK[$listing->education ?? 'none'] ?? 0,
            educationLabel: $listing->education !== null && $listing->education !== 'none' ? (string) __('work.education.'.$listing->education) : null,
            licence: $listing->licence,
            languages: $listing->languages ?? [],
            type: $listing->type,
            sector: $listing->organisation->sector,
            position: $listing->latitude !== null && $listing->longitude !== null ? [(float) $listing->latitude, (float) $listing->longitude] : null,
        );
    }

    /**
     * @param  array<int, mixed>  $names
     * @return list<string>
     */
    public function canonical(array $names): array
    {
        $this->synonyms ??= DB::table('work_skill_synonyms')->pluck('canonical', 'phrase')->map(static fn ($v): string => (string) $v)->all();

        return array_values(array_unique(array_map(function (mixed $name): string {
            $key = mb_strtolower(trim((string) $name));

            return $this->synonyms[$key] ?? $key;
        }, $names)));
    }

    /** @return array{0: float, 1: float}|null */
    private function origin(?string $hubId, ?int $municipalityId): ?array
    {
        $hub = $hubId !== null ? DB::table('hubs')->where('id', $hubId)->first(['latitude', 'longitude']) : null;
        if ($hub !== null && $hub->latitude !== null && $hub->longitude !== null) {
            return [(float) $hub->latitude, (float) $hub->longitude];
        }

        $m = $municipalityId !== null ? DB::table('municipalities')->where('id', $municipalityId)->first(['latitude', 'longitude']) : null;

        return $m !== null && $m->latitude !== null && $m->longitude !== null ? [(float) $m->latitude, (float) $m->longitude] : null;
    }

    /** Months of experience from dates, or a rough duration such as "about 2 years" (default 6). */
    public static function months(?string $started, ?string $ended, ?string $duration): int
    {
        if ($started !== null) {
            $from = CarbonImmutable::createFromFormat('Y-m', $started);
            $to = $ended !== null ? CarbonImmutable::createFromFormat('Y-m', $ended) : CarbonImmutable::now();

            return $from !== null && $to !== null ? max(1, (int) $from->diffInMonths($to)) : 6;
        }

        if ($duration !== null && preg_match('/(\d+(?:[.,]\d)?)\s*(year|yr|month|week)/i', $duration, $m) === 1) {
            $n = (float) str_replace(',', '.', $m[1]);

            return (int) round(match (strtolower(substr($m[2], 0, 1))) {
                'y' => $n * 12, 'm' => $n, default => $n / 4
            });
        }

        if ($duration !== null && preg_match('/\b(a|one) year\b/i', $duration) === 1) {
            return 12;
        }

        return 6;
    }
}
