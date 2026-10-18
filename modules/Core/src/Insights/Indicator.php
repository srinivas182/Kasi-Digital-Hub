<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

use Carbon\CarbonImmutable;

/**
 * Extension point (ADR-028): an outcome indicator a portal publishes for funders and coordinators. Values are
 * aggregates for a set of hubs and a period - never data about individuals. Tag implementations with TAG.
 */
interface Indicator
{
    public const TAG = 'kasi.indicators';

    /** e.g. "work.hires" - its label and definition are the translations insights.indicator.<key>(.definition). */
    public function key(): string;

    /** count | cents */
    public function unit(): string;

    /**
     * Value per hub for the period (only hubs with a value are returned). Hub-less records are under "".
     *
     * @param  list<string>|null  $hubIds  null = everywhere
     * @param  AgeRange|null  $ages  only people in this age range (e.g. a programme's target group or an age band)
     * @return array<string, int>
     */
    public function byHub(?array $hubIds, CarbonImmutable $from, CarbonImmutable $to, ?AgeRange $ages = null): array;
}
