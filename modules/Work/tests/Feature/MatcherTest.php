<?php

declare(strict_types=1);

use Modules\Core\Ai\Embeddings\Embeddings;
use Modules\Work\Database\Seeders\SkillSynonymSeeder;
use Modules\Work\Matching\ListingFacts;
use Modules\Work\Matching\Matcher;
use Modules\Work\Matching\MatchFacts;
use Modules\Work\Matching\SeekerFacts;

function seekerFacts(array $o = []): SeekerFacts
{
    return new SeekerFacts(...[
        'userId' => 'u1', 'skills' => ['cash handling', 'customer service'], 'experienceMonths' => 18, 'hasExperience' => true,
        'educationRank' => 2, 'educationVerified' => true, 'licence' => null, 'languages' => ['English', 'Xitsonga'],
        'workTypes' => ['part_time'], 'sectors' => ['retail'], 'maxTravelKm' => 30, 'origin' => [-23.27, 30.78], ...$o,
    ]);
}

function listingFacts(array $o = []): ListingFacts
{
    return new ListingFacts(...[
        'listingId' => 'l1', 'mustSkills' => ['cash handling', 'customer service'], 'niceSkills' => [], 'experience' => 'none',
        'educationRank' => 2, 'educationLabel' => 'Matric', 'licence' => null, 'languages' => ['English'], 'type' => 'part_time',
        'sector' => 'retail', 'position' => [-23.30, 30.72], ...$o,
    ]);
}

beforeEach(fn () => $this->seed(SkillSynonymSeeder::class));

it('gives a full score with plain reasons when everything fits', function (): void {
    $result = app(Matcher::class)->score(seekerFacts(), listingFacts());

    expect($result->score)->toBe(100)->and($result->gaps)->toBe([])->and($result->distanceKm)->toBe(7)
        ->and(array_column($result->reasons, 'key'))->toContain('work.match.r.skills', 'work.match.r.no_experience_needed', 'work.match.r.education_verified', 'work.match.r.distance');
});

it('counts synonyms as the same skill', function (): void {
    $facts = app(MatchFacts::class);

    expect($facts->canonical(['Till operation', 'Serving customers', 'Welding']))->toBe(['cash handling', 'customer service', 'welding']);
});

it('gives only half credit for AI-similar skills, so they never outweigh real matches', function (): void {
    $exact = app(Matcher::class)->score(seekerFacts(['skills' => ['forklift driving']]), listingFacts(['mustSkills' => ['forklift driving']]));
    $similar = app(Matcher::class)->score(seekerFacts(['skills' => ['driving forklift trucks']]), listingFacts(['mustSkills' => ['forklift driving']]));
    $none = app(Matcher::class)->score(seekerFacts(['skills' => ['cooking']]), listingFacts(['mustSkills' => ['forklift driving']]));

    expect($exact->score)->toBeGreaterThan($similar->score)->and($similar->score)->toBeGreaterThan($none->score)
        ->and($exact->score - $none->score)->toBe(35)->and($similar->score - $none->score)->toBeLessThanOrEqual(18);
});

it('counts informal experience and rough durations', function (): void {
    expect(MatchFacts::months(null, null, 'about 2 years'))->toBe(24)
        ->and(MatchFacts::months(null, null, 'weekends for a year'))->toBe(12)
        ->and(MatchFacts::months(null, null, 'a few months'))->toBe(6);

    $some = app(Matcher::class)->score(seekerFacts(['experienceMonths' => 6]), listingFacts(['experience' => 'some']));
    expect(array_column($some->reasons, 'key'))->toContain('work.match.r.has_experience');
});

it('treats a missing licence or minimum education as a gap, not a hidden rule', function (): void {
    $result = app(Matcher::class)->score(seekerFacts(['educationRank' => 1, 'licence' => null]), listingFacts(['licence' => 'B']));

    expect(array_column($result->gaps, 'key'))->toBe(['work.match.g.education', 'work.match.g.licence']);
    expect(app(Matcher::class)->score(seekerFacts(['licence' => 'C1']), listingFacts(['licence' => 'B']))->gaps)->toBe([]); // C1 covers B
});

it('leaves out jobs beyond the travel distance', function (): void {
    $far = app(Matcher::class)->score(seekerFacts(['maxTravelKm' => 10]), listingFacts(['position' => [-26.27, 27.86]]));

    expect($far->outOfRange)->toBeTrue();
});

it('cannot see names, age or other protected details - the facts do not contain them', function (): void {
    $fields = array_map(static fn (ReflectionProperty $p): string => $p->getName(), (new ReflectionClass(SeekerFacts::class))->getProperties());

    expect($fields)->not->toContain('name')->not->toContain('firstName')->not->toContain('dateOfBirth')->not->toContain('age')
        ->not->toContain('gender')->not->toContain('race')->not->toContain('phone')->not->toContain('idNumber');
});

it('caches embeddings so each phrase is only embedded once', function (): void {
    $embeddings = app(Embeddings::class);
    $embeddings->similarity('cash handling', 'handling cash payments');
    app()->forgetInstance(Embeddings::class);
    app(Embeddings::class)->similarity('cash handling', 'customer service');

    expect(DB::table('ai_embeddings')->count())->toBe(3);
});
