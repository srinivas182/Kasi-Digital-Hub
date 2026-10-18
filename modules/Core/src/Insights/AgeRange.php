<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

use Carbon\CarbonImmutable;

/** People aged from $min to $max (inclusive) today - a filter the database can apply (S22). */
final readonly class AgeRange
{
    public function __construct(public int $min, public int $max) {}

    /** @return array{0: string, 1: string} date-of-birth bounds (oldest, youngest) */
    public function birthBetween(): array
    {
        $today = CarbonImmutable::today();

        return [$today->subYears($this->max + 1)->addDay()->toDateString(), $today->subYears($this->min)->toDateString()];
    }
}
