<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates for funders and coordinators (ADR-028): every indicator for a set of hubs and a period, with
 * breakdowns by hub, city, province, metro/rural and age band, and small numbers suppressed. No data about
 * individuals ever leaves this class.
 */
final class Insights
{
    public const MIN_CELL = 10;

    public const CACHE_MINUTES = 5;

    public const AGE_BANDS = ['16-17' => [16, 17], '18-24' => [18, 24], '25-29' => [25, 29], '30-35' => [30, 35], '36+' => [36, 200]];

    /** @return array<string, Indicator> */
    public function indicators(): array
    {
        $all = [];
        foreach (app()->tagged(Indicator::TAG) as $indicator) {
            /** @var Indicator $indicator */
            $all[$indicator->key()] = $indicator;
        }
        ksort($all);

        return $all;
    }

    /**
     * Raw totals (not suppressed) - for snapshots and target progress. Callers must suppress before showing.
     *
     * @param  list<string>|null  $hubIds
     * @param  list<string>|null  $only
     * @return array<string, array{total: int, byHub: array<string, int>}>
     */
    public function totals(?array $hubIds, CarbonImmutable $from, CarbonImmutable $to, ?array $only = null, ?AgeRange $ages = null): array
    {
        // S22: aggregates only, so a shared cache is safe; 5 minutes keeps "live" figures fresh enough while a
        // busy funder or regional page doesn't recount every time (0.8-1.6 s at 500k events, measured).
        $key = 'kasi:insights:'.md5((string) json_encode([$hubIds, $from->startOfMinute()->toIso8601String(), $to->startOfMinute()->toIso8601String(), $only, $ages]));

        return Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), fn (): array => $this->count($hubIds, $from, $to, $only, $ages));
    }

    /**
     * @param  list<string>|null  $hubIds
     * @param  list<string>|null  $only
     * @return array<string, array{total: int, byHub: array<string, int>}>
     */
    private function count(?array $hubIds, CarbonImmutable $from, CarbonImmutable $to, ?array $only, ?AgeRange $ages): array
    {
        $out = [];
        foreach ($this->indicators() as $key => $indicator) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }
            $byHub = $indicator->byHub($hubIds, $from, $to, $ages);
            $out[$key] = ['total' => array_sum($byHub), 'byHub' => $byHub];
        }

        return $out;
    }

    /**
     * Breakdown of one indicator by area and age band, suppressed.
     *
     * @param  list<string>|null  $hubIds
     * @return array{hub: array<string, int|string>, city: array<string, int|string>, province: array<string, int|string>, tier: array<string, int|string>, age: array<string, int|string>}
     */
    public function breakdown(string $key, ?array $hubIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $indicator = $this->indicators()[$key] ?? null;
        if ($indicator === null) {
            return ['hub' => [], 'city' => [], 'province' => [], 'tier' => [], 'age' => []];
        }
        $byHub = $indicator->byHub($hubIds, $from, $to);
        $hubs = DB::table('hubs')->join('municipalities', 'municipalities.id', '=', 'hubs.municipality_id')->join('provinces', 'provinces.id', '=', 'municipalities.province_id')
            ->whereIn('hubs.id', array_keys($byHub))->get(['hubs.id', 'hubs.name', 'municipalities.name as city', 'municipalities.category', 'provinces.name as province'])->keyBy('id');
        $group = static function (callable $label) use ($byHub, $hubs): array {
            $g = [];
            foreach ($byHub as $hubId => $value) {
                $h = $hubs->get($hubId);
                $name = $h === null ? 'national' : (string) $label($h);
                $g[$name] = ($g[$name] ?? 0) + $value;
            }
            ksort($g);

            return $g;
        };
        $age = [];
        if ($indicator->unit() === 'count') {
            foreach (self::AGE_BANDS as $band => [$min, $max]) {
                $age[$band] = array_sum($indicator->byHub($hubIds, $from, $to, new AgeRange($min, $max)));
            }
        }

        return [
            'hub' => self::suppress($group(static fn (\stdClass $h): string => (string) ((array) $h)['name'])),
            'city' => self::suppress($group(static fn (\stdClass $h): string => (string) ((array) $h)['city'])),
            'province' => self::suppress($group(static fn (\stdClass $h): string => (string) ((array) $h)['province'])),
            'tier' => self::suppress($group(static fn (\stdClass $h): string => ((array) $h)['category'] === 'metro' ? 'metro' : 'rural')),
            'age' => self::suppress($age),
        ];
    }

    /**
     * Small-number suppression: any count from 1 to 9 is shown as "<10" (zero stays zero).
     *
     * @param  array<string, int>  $values
     * @return array<string, int|string>
     */
    public static function suppress(array $values): array
    {
        return array_map(static fn (int $v): int|string => self::cell($v), $values);
    }

    public static function cell(int $value): int|string
    {
        return $value > 0 && $value < self::MIN_CELL ? '<10' : $value;
    }

    /**
     * @param  list<string>|null  $hubIds
     * @return list<string> hub ids in a province / city / list of hubs
     */
    public static function hubsIn(?int $provinceId = null, ?int $municipalityId = null, ?array $hubIds = null): array
    {
        $q = DB::table('hubs')->join('municipalities', 'municipalities.id', '=', 'hubs.municipality_id');
        if ($provinceId !== null) {
            $q->where('municipalities.province_id', $provinceId);
        }
        if ($municipalityId !== null) {
            $q->where(fn ($w) => $w->where('hubs.municipality_id', $municipalityId)->orWhere('municipalities.district_id', $municipalityId));
        }
        if ($hubIds !== null) {
            $q->whereIn('hubs.id', $hubIds);
        }

        return array_values(array_map('strval', $q->pluck('hubs.id')->all()));
    }
}
