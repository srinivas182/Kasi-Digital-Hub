<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

/**
 * Extension point (ADR-028): an operational count per hub for coordinators, e.g. documents waiting for
 * verification or referrals near their no-response deadline. Counts only. Tag implementations with TAG.
 */
interface HealthSignal
{
    public const TAG = 'kasi.health_signals';

    public function key(): string;

    /**
     * @param  list<string>  $hubIds
     * @return array<string, int> hub id => count
     */
    public function counts(array $hubIds): array;
}
