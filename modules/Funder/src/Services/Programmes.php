<?php

declare(strict_types=1);

namespace Modules\Funder\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Insights\AgeRange;
use Modules\Core\Insights\Insights;
use Modules\Funder\Models\Programme;

/**
 * Programme impact (ADR-028): aggregate indicators for the programme's hubs and target group, frozen monthly
 * snapshots (on the 5th, for the month before), target progress, and target changes approved by KasiHub.
 */
final readonly class Programmes
{
    public function __construct(private Insights $insights, private AuditLogger $audit) {}

    /** @return list<string> */
    public function hubs(Programme $p): array
    {
        $s = $p->scope;
        $provinces = $s['province_ids'] ?? [];
        $cities = $s['municipality_ids'] ?? [];
        $hubs = $s['hub_ids'] ?? [];
        if ($provinces === [] && $cities === [] && $hubs === []) {
            return Insights::hubsIn();
        }
        $all = [];
        foreach ($provinces as $id) {
            $all = [...$all, ...Insights::hubsIn(provinceId: (int) $id)];
        }
        foreach ($cities as $id) {
            $all = [...$all, ...Insights::hubsIn(municipalityId: (int) $id)];
        }

        return array_values(array_unique([...$all, ...array_map('strval', $hubs)]));
    }

    /** The programme's target group: people whose age (today) is within its range. */
    public function ages(Programme $p): AgeRange
    {
        return new AgeRange($p->age_min, $p->age_max);
    }

    /** @return list<string> */
    public function indicators(Programme $p): array
    {
        return array_values(array_map('strval', DB::table('fund_targets')->where('programme_id', $p->id)->pluck('indicator')->all()));
    }

    /**
     * Values for a month: frozen snapshot when there is one, else live (provisional).
     *
     * @return array{values: array<string, int>, byHub: array<string, array<string, int>>, provisional: bool}
     */
    public function month(Programme $p, string $period): array
    {
        $frozen = DB::table('fund_snapshots')->where(['scope_type' => 'programme', 'scope_id' => $p->id, 'period' => $period])->get();
        if ($frozen->isNotEmpty()) {
            return ['values' => $frozen->mapWithKeys(static fn (object $r): array => [(string) $r->indicator => (int) $r->value])->all(),
                'byHub' => $frozen->mapWithKeys(static fn (object $r): array => [(string) $r->indicator => (array) json_decode((string) $r->by_hub, true)])->all(), 'provisional' => false];
        }
        [$from, $to] = self::bounds($period);
        $totals = $this->insights->totals($this->hubs($p), $from, $to, $this->indicators($p), $this->ages($p));

        return ['values' => array_map(static fn (array $t): int => $t['total'], $totals), 'byHub' => array_map(static fn (array $t): array => $t['byHub'], $totals), 'provisional' => true];
    }

    /**
     * Progress since the start: sum of monthly values against targets.
     *
     * @return array<string, array{value: int, target: int, percent: int}>
     */
    public function progress(Programme $p): array
    {
        $targets = DB::table('fund_targets')->where('programme_id', $p->id)->pluck('target', 'indicator');
        $sum = array_fill_keys(array_keys($targets->all()), 0);
        foreach ($this->periods($p) as $period) {
            foreach ($this->month($p, $period)['values'] as $key => $value) {
                $sum[$key] = ($sum[$key] ?? 0) + $value;
            }
        }
        $out = [];
        foreach ($targets as $key => $target) {
            $out[(string) $key] = ['value' => $sum[$key] ?? 0, 'target' => (int) $target, 'percent' => (int) $target === 0 ? 0 : (int) min(999, floor(100 * ($sum[$key] ?? 0) / (int) $target))];
        }

        return $out;
    }

    /** @return list<string> months from the start to now (or the end) */
    public function periods(Programme $p): array
    {
        $periods = [];
        $month = $p->starts_on->startOfMonth();
        $last = CarbonImmutable::now()->min($p->ends_on)->startOfMonth();
        while ($month->lte($last)) {
            $periods[] = $month->format('Y-m');
            $month = $month->addMonth();
        }

        return $periods;
    }

    /** Freeze last month's numbers for every active programme (idempotent). */
    public function freeze(string $period): int
    {
        $count = 0;
        Programme::query()->whereIn('status', ['active', 'closed'])->get()->each(function (Programme $p) use ($period, &$count): void {
            if (! in_array($period, $this->periods($p), true) || DB::table('fund_snapshots')->where(['scope_type' => 'programme', 'scope_id' => $p->id, 'period' => $period])->exists()) {
                return;
            }
            $month = $this->month($p, $period);
            foreach ($month['values'] as $indicator => $value) {
                DB::table('fund_snapshots')->insert(['scope_type' => 'programme', 'scope_id' => $p->id, 'period' => $period, 'indicator' => $indicator, 'value' => $value,
                    'by_hub' => json_encode($month['byHub'][$indicator] ?? []), 'frozen_at' => now()]);
            }
            $count++;
        });

        return $count;
    }

    public function requestTarget(Programme $p, string $indicator, int $target, string $reason, User $by): void
    {
        if (! array_key_exists($indicator, $this->insights->indicators())) {
            throw new DomainException(__('funder.target.unknown'));
        }
        DB::table('fund_target_changes')->insert(['programme_id' => $p->id, 'indicator' => $indicator, 'target' => $target, 'reason' => $reason, 'requested_by' => $by->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('funder.target_requested', meta: ['programme' => $p->id, 'indicator' => $indicator, 'target' => $target], actor: $by);
    }

    public function decideTarget(int $changeId, User $by, bool $approve): void
    {
        $c = DB::table('fund_target_changes')->where('id', $changeId)->where('status', 'pending')->first();
        if ($c === null || $c->requested_by === $by->id) {
            throw new DomainException(__('commercial.approval.not_own'));
        }
        DB::table('fund_target_changes')->where('id', $changeId)->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'updated_at' => now()]);
        if ($approve) {
            DB::table('fund_targets')->updateOrInsert(['programme_id' => $c->programme_id, 'indicator' => $c->indicator], ['target' => $c->target]);
        }
        $this->audit->record('funder.target_'.($approve ? 'approved' : 'rejected'), meta: ['change' => $changeId], actor: $by);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function bounds(string $period): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01', 'Africa/Johannesburg')?->startOfDay() ?? CarbonImmutable::now()->startOfMonth();

        return [$start, $start->endOfMonth()];
    }
}
