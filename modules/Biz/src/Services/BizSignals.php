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
        // One query for both (S29 query budget): months with entries since the start of the month 3 months ago, and
        // trading money in over the last 3 months.
        $rows = DB::table('biz_entries')->where('business_id', $businessId)->whereNull('deleted_at')->where('hidden', false)->where('occurred_on', '>=', $from->toDateString())
            ->get(['occurred_on', 'direction', 'category', 'amount_cents']);
        $months = $rows->map(static fn (object $r): string => substr((string) $r->occurred_on, 0, 7))->unique()->count();
        $since = now()->subMonthsNoOverflow(3)->toDateString();
        $in = (int) $rows->filter(static fn (object $r): bool => $r->direction === 'in' && ! in_array($r->category, Books::NOT_TRADING, true) && (string) $r->occurred_on >= $since)
            ->sum('amount_cents');
        $monthly = intdiv($in, 3);
        $band = '50k_plus';
        foreach (self::BANDS as $limit => $name) {
            if ($monthly < $limit) {
                $band = $name;
                break;
            }
        }

        return ['biz.keeps_records' => $months >= 3, 'biz.turnover_band' => $months > 0 ? $band : null,
            // S29: the compliance calendar and stock.
            // One query: has a calendar, and nothing overdue.
            'biz.compliance_up_to_date' => (static function () use ($businessId): bool {
                $c = DB::table('biz_obligations')->where('business_id', $businessId)
                    ->selectRaw("count(*) as total, sum(case when status = 'due' and due_on < ? then 1 else 0 end) as overdue", [now()->toDateString()])->first();

                return $c !== null && (int) $c->total > 0 && (int) $c->overdue === 0;
            })(),
            'biz.tracks_stock' => DB::table('biz_stock_moves')->join('biz_stock_items', 'biz_stock_items.id', '=', 'biz_stock_moves.item_id')
                ->where('biz_stock_items.business_id', $businessId)->where('biz_stock_moves.created_at', '>=', now()->subDays(60))->exists()];
    }
}
