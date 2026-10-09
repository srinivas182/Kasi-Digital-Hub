<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

/**
 * A score with plain reasons. Reasons and gaps are translation keys with parameters, so they
 * show in the person's language.
 */
final readonly class MatchResult
{
    /**
     * @param  list<array{key: string, params: array<string, string|int>}>  $reasons
     * @param  list<array{key: string, params: array<string, string|int>}>  $gaps
     */
    public function __construct(
        public int $score,
        public array $reasons,
        public array $gaps,
        public ?int $distanceKm,
        public bool $outOfRange,
    ) {}
}
