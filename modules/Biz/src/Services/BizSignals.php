<?php

declare(strict_types=1);

namespace Modules\Biz\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\BusinessSignals;

/**
 * What KasiBiz tells partners about a business (S28): whether it keeps records (entries in at least 3 of the last 4
 * months) and a turnover band from its average trading money in - never exact figures.
 */
final class BizSignals implements BusinessSignals
{
    public const BANDS = [500000 => 'under_5k', 2000000 => '5k_20k', 5000000 => '20k_50k'];

    public function signals(string $businessId): array
    {
        $from = now()->subMonthsNoOverflow(3)->startOfMonth();
        $months = DB::table('biz_entries')->where('business_id', $businessId)->whereNull('deleted_at')->where('hidden', false)->where('occurred_on', '>=', $from->toDateString())
            ->pluck('occurred_on')->map(static fn ($d): string => substr((string) $d, 0, 7))->unique()->count();
        $in = (int) DB::table('biz_entries')->where('business_id', $businessId)->whereNull('deleted_at')->where('hidden', false)->where('direction', 'in')
            ->whereNotIn('category', Books::NOT_TRADING)->where('occurred_on', '>=', now()->subMonthsNoOverflow(3)->toDateString())->sum('amount_cents');
        $monthly = intdiv($in, 3);
        $band = '50k_plus';
        foreach (self::BANDS as $limit => $name) {
            if ($monthly < $limit) {
                $band = $name;
                break;
            }
        }

        return ['biz.keeps_records' => $months >= 3, 'biz.turnover_band' => $months > 0 ? $band : null];
    }
}
