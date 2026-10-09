<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

/** What the matcher knows about a job advert. */
final readonly class ListingFacts
{
    /**
     * @param  list<string>  $mustSkills  Canonical, lower-case
     * @param  list<string>  $niceSkills
     * @param  list<string>  $languages
     * @param  array{0: float, 1: float}|null  $position
     */
    public function __construct(
        public string $listingId,
        public array $mustSkills,
        public array $niceSkills,
        public string $experience,
        public int $educationRank,
        public ?string $educationLabel,
        public ?string $licence,
        public array $languages,
        public string $type,
        public ?string $sector,
        public ?array $position,
    ) {}
}
