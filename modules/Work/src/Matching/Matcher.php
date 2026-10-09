<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use Modules\Core\Ai\Embeddings\Embeddings;

/**
 * Explainable, rule-based match score (0-100) between a job seeker and a job advert (ADR-019).
 *
 *   must-have skills 35 · experience 15 · education 15 · distance 15 · kind of work and sector 10
 *   nice-to-have skills 5 · languages 5
 *
 * Similar skills count through a curated synonym list; AI embeddings add at most half credit for
 * a skill and can never outweigh real matches. Distance beyond the person's travel limit is out of
 * range. A missing required licence or minimum education is a "gap" (shown to the person; such
 * people are not suggested to employers).
 */
final class Matcher
{
    public const EMBEDDING_THRESHOLD = 0.8;

    /** Which licences satisfy which requirement. */
    private const LICENCES = [
        'A1' => ['A1', 'A'], 'A' => ['A'],
        'B' => ['B', 'C1', 'C', 'EB', 'EC1', 'EC'], 'C1' => ['C1', 'C', 'EC1', 'EC'], 'C' => ['C', 'EC'],
        'EB' => ['EB', 'EC1', 'EC'], 'EC1' => ['EC1', 'EC'], 'EC' => ['EC'],
    ];

    public function __construct(private readonly Embeddings $embeddings) {}

    public function score(SeekerFacts $seeker, ListingFacts $listing): MatchResult
    {
        $reasons = [];
        $gaps = [];
        $score = 0.0;

        // Must-have skills (35)
        if ($listing->mustSkills === []) {
            $score += 35;
        } else {
            $credit = $this->skillCredit($seeker->skills, $listing->mustSkills);
            $score += 35 * $credit['credit'] / count($listing->mustSkills);
            $reasons[] = ['key' => 'work.match.r.skills', 'params' => ['have' => $credit['matched'], 'need' => count($listing->mustSkills)]];
        }

        // Experience (15) - informal work counts as experience.
        [$points, $reason] = match ($listing->experience) {
            'none' => [15, 'work.match.r.no_experience_needed'],
            'some' => $seeker->hasExperience ? [15, 'work.match.r.has_experience'] : [0, null],
            '1_year' => $seeker->experienceMonths >= 12 ? [15, 'work.match.r.experience_years'] : ($seeker->experienceMonths >= 6 ? [8, 'work.match.r.some_experience'] : [0, null]),
            default => $seeker->experienceMonths >= 24 ? [15, 'work.match.r.experience_years'] : ($seeker->experienceMonths >= 12 ? [8, 'work.match.r.some_experience'] : [0, null]),
        };
        $score += $points;
        if ($reason !== null) {
            $reasons[] = ['key' => $reason, 'params' => ['years' => intdiv($seeker->experienceMonths, 12)]];
        }

        // Education (15)
        if ($listing->educationRank === 0) {
            $score += 15;
        } elseif ($seeker->educationRank >= $listing->educationRank) {
            $score += $seeker->educationVerified ? 15 : 12;
            $reasons[] = ['key' => $seeker->educationVerified ? 'work.match.r.education_verified' : 'work.match.r.education', 'params' => ['level' => (string) $listing->educationLabel]];
        } else {
            $gaps[] = ['key' => 'work.match.g.education', 'params' => ['level' => (string) $listing->educationLabel]];
        }

        // Licence (gap only)
        if ($listing->licence !== null && ! in_array($seeker->licence, self::LICENCES[$listing->licence] ?? [$listing->licence], true)) {
            $gaps[] = ['key' => 'work.match.g.licence', 'params' => ['code' => $listing->licence]];
        }

        // Distance (15)
        $distance = null;
        $outOfRange = false;
        if ($seeker->origin !== null && $listing->position !== null) {
            $distance = (int) round(self::km($seeker->origin, $listing->position));
            $limit = max(1, $seeker->maxTravelKm ?? 50);
            if ($distance > $limit) {
                $outOfRange = true;
            } else {
                $score += $distance <= $limit / 3 ? 15 : 5 + 10 * (1 - ($distance - $limit / 3) / ($limit * 2 / 3));
                $reasons[] = ['key' => 'work.match.r.distance', 'params' => ['km' => $distance]];
            }
        } else {
            $score += 7;
        }

        // Kind of work (6) and sector (4)
        if ($seeker->workTypes === [] || in_array(self::typeGroup($listing->type), array_map(self::typeGroup(...), $seeker->workTypes), true)) {
            $score += 6;
            if ($seeker->workTypes !== []) {
                $reasons[] = ['key' => 'work.match.r.type', 'params' => []];
            }
        }
        if ($seeker->sectors === [] || ($listing->sector !== null && in_array($listing->sector, $seeker->sectors, true))) {
            $score += 4;
        }

        // Nice-to-have skills (5) and languages (5)
        $score += $listing->niceSkills === [] ? 5 : 5 * $this->skillCredit($seeker->skills, $listing->niceSkills)['credit'] / count($listing->niceSkills);
        if ($listing->languages === []) {
            $score += 5;
        } else {
            $shared = count(array_intersect(array_map('mb_strtolower', $listing->languages), array_map('mb_strtolower', $seeker->languages)));
            $score += 5 * $shared / count($listing->languages);
            if ($shared > 0) {
                $reasons[] = ['key' => 'work.match.r.languages', 'params' => ['count' => $shared]];
            }
        }

        return new MatchResult((int) max(0, min(100, round($score))), $reasons, $gaps, $distance, $outOfRange);
    }

    /**
     * Full credit for the same skill (after synonyms), half credit for an AI-similar skill.
     *
     * @param  list<string>  $have
     * @param  list<string>  $want
     * @return array{credit: float, matched: int}
     */
    private function skillCredit(array $have, array $want): array
    {
        $credit = 0.0;
        $matched = 0;
        $unmatched = [];

        foreach ($want as $skill) {
            if (in_array($skill, $have, true)) {
                $credit += 1.0;
                $matched++;
            } else {
                $unmatched[] = $skill;
            }
        }

        if ($unmatched !== [] && $have !== []) {
            $this->embeddings->vectors([...$have, ...$unmatched]);
            foreach ($unmatched as $skill) {
                $best = 0.0;
                foreach ($have as $own) {
                    $best = max($best, $this->embeddings->similarity($skill, $own));
                }
                if ($best >= self::EMBEDDING_THRESHOLD) {
                    $credit += 0.5;
                    $matched++;
                }
            }
        }

        return ['credit' => $credit, 'matched' => $matched];
    }

    private static function typeGroup(string $type): string
    {
        return match ($type) {
            'piece_work', 'temporary' => 'short',
            'learnership', 'internship' => 'training',
            default => $type,
        };
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public static function km(array $a, array $b): float
    {
        $dLat = deg2rad($b[0] - $a[0]);
        $dLon = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLon / 2) ** 2;

        return 2 * 6371 * asin(min(1, sqrt($h)));
    }
}
