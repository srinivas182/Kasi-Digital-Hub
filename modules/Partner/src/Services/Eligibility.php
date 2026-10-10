<?php

declare(strict_types=1);

namespace Modules\Partner\Services;

use Modules\Partner\Models\Offer;

/**
 * Clear, explainable eligibility (ADR-025): each criterion is met or not met, with a reason and - where
 * KasiStart can fix it - the step that does. No ranking.
 */
final class Eligibility
{
    /**
     * @param  array<string, mixed>  $facts  BusinessFacts::facts()
     * @return array{eligible: bool, met: int, total: int, checks: list<array{key: string, met: bool, params: array<string, string|int>, step: string|null}>}
     */
    public function check(Offer $offer, array $facts): array
    {
        $c = $offer->criteria;
        $checks = [];
        $add = static function (string $key, bool $met, array $params = [], ?string $step = null) use (&$checks): void {
            $checks[] = ['key' => $key, 'met' => $met, 'params' => $params, 'step' => $met ? null : $step];
        };

        if (! empty($c['stages'])) {
            $add('stage', in_array($facts['stage'], $c['stages'], true), ['list' => implode(', ', $c['stages'])]);
        }
        if (! empty($c['sectors'])) {
            $add('sector', in_array($facts['sector'], $c['sectors'], true), ['list' => implode(', ', $c['sectors'])]);
        }
        if (! empty($c['forms'])) {
            $add('form', in_array($facts['legal_form'], $c['forms'], true), ['list' => implode(', ', $c['forms'])], 'choose_form');
        }
        if (! empty($c['province_ids'])) {
            $add('province', in_array((int) $facts['province_id'], array_map('intval', $c['province_ids']), true));
        }
        if (! empty($c['municipality_ids'])) {
            $add('municipality', in_array((int) $facts['municipality_id'], array_map('intval', $c['municipality_ids']), true));
        }
        if (! empty($c['hub_ids'])) {
            $add('hub', in_array((string) $facts['hub_id'], $c['hub_ids'], true));
        }
        if (isset($c['age_max'])) {
            $add('age_max', $facts['youngest_owner_age'] !== null && $facts['youngest_owner_age'] <= (int) $c['age_max'], ['age' => (int) $c['age_max']]);
        }
        if (isset($c['age_min'])) {
            $add('age_min', $facts['youngest_owner_age'] !== null && $facts['youngest_owner_age'] >= (int) $c['age_min'], ['age' => (int) $c['age_min']]);
        }
        if (! empty($c['turnover'])) {
            $add('turnover', in_array($facts['turnover_band'], $c['turnover'], true));
        }
        foreach ((array) ($c['steps'] ?? []) as $step) {
            $add('step', in_array($step, $facts['steps_done'], true), ['step' => (string) $step], (string) $step);
        }
        if (! empty($c['min_readiness'])) {
            $add('readiness', $facts['readiness'] >= (int) $c['min_readiness'], ['score' => (int) $c['min_readiness']]);
        }

        $met = count(array_filter($checks, static fn (array $x): bool => $x['met']));

        return ['eligible' => $met === count($checks), 'met' => $met, 'total' => count($checks), 'checks' => $checks];
    }
}
