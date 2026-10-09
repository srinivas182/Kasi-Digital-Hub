<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

/**
 * Everything the matcher may know about a job seeker - and nothing else. No name, age, date of
 * birth, gender, race, photo or ID: the matcher cannot use what it never receives.
 */
final readonly class SeekerFacts
{
    /**
     * @param  list<string>  $skills  Canonical, lower-case skill names
     * @param  list<string>  $languages
     * @param  list<string>  $workTypes
     * @param  list<string>  $sectors
     * @param  array{0: float, 1: float}|null  $origin
     */
    public function __construct(
        public string $userId,
        public array $skills,
        public int $experienceMonths,
        public bool $hasExperience,
        public int $educationRank,
        public bool $educationVerified,
        public ?string $licence,
        public array $languages,
        public array $workTypes,
        public array $sectors,
        public ?int $maxTravelKm,
        public ?array $origin,
    ) {}
}
