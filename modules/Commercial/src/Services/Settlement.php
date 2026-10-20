<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Commercial\Models\LedgerEntry;
use Modules\Commercial\Payments\PaymentGateway;
use Modules\Core\Structure\Models\Hub;

/**
 * Monthly hub statements and payouts, and daily reconciliation against the provider (ADR-027).
 *
 * Online sales are split at source (the hub's share goes straight to its sub-account), so what Kasi still
 * owes a hub - or a hub owes Kasi - is: delivery fees earned, minus royalties on cash sales.
 */
final readonly class Settlement
{
    public function __construct(private PaymentGateway $gateway) {}

    /** @return array{sales: int, online_hub: int, cash: int, royalty_due: int, delivery_fees: int, market_shares: int, balance: int} */
    public function statement(Hub $hub, string $period): array
    {
        [$from, $to] = [$period.'-01 00:00:00', CarbonImmutable::parse($period.'-01')->endOfMonth()->toDateTimeString()];
        $orders = DB::table('com_orders')->where('hub_id', $hub->id)->whereIn('status', ['paid', 'part_refunded', 'refunded'])->whereBetween('paid_at', [$from, $to])->get();
        $entries = LedgerEntry::query()->where('hub_id', $hub->id)->whereBetween('created_at', [$from, $to]);
        $royalty = (int) (clone $entries)->where('kind', 'royalty_due')->sum('amount_cents');
        $fees = (int) (clone $entries)->whereNotNull('invoice_id')->where('account', 'hub')->sum('amount_cents');
        // S26: on marketplace sales the seller is paid at source and Kasi's account receives the Kasi and hub
        // shares, so the hub's share (net of refunds) is owed to the hub.
        $market = (int) (clone $entries)->where('account', 'hub')->whereIn('kind', ['sale', 'refund'])
            ->whereIn('order_id', DB::table('com_orders')->whereNotNull('payee_ref')->where('hub_id', $hub->id)->pluck('id'))->sum('amount_cents');

        return [
            'sales' => (int) $orders->sum('total_cents') - (int) $orders->sum('refunded_cents'),
            'online_hub' => (int) $orders->where('channel', 'online')->sum(static fn (object $o): int => (int) ((array) json_decode((string) $o->shares, true))['hub']),
            'cash' => (int) $orders->where('channel', 'pos')->sum('total_cents'),
            'royalty_due' => $royalty, 'delivery_fees' => $fees, 'market_shares' => $market, 'balance' => $fees + $royalty + $market,
        ];
    }

    /** Plan the month's payouts: positive balances are paid to the hub, negative ones carried as owed. */
    public function payouts(string $period): int
    {
        $count = 0;
        Hub::query()->get()->each(function (Hub $hub) use ($period, &$count): void {
            $carried = (int) DB::table('com_payouts')->where('hub_id', $hub->id)->where('status', 'carried')->where('period', '<', $period)->sum('amount_cents');
            $balance = $this->statement($hub, $period)['balance'] + $carried;
            if ($balance === 0 || DB::table('com_payouts')->where('hub_id', $hub->id)->where('period', $period)->exists()) {
                return;
            }
            DB::table('com_payouts')->where('hub_id', $hub->id)->where('status', 'carried')->where('period', '<', $period)->update(['status' => 'settled', 'updated_at' => now()]);
            DB::table('com_payouts')->insert(['hub_id' => $hub->id, 'period' => $period, 'amount_cents' => $balance, 'status' => $balance > 0 ? 'planned' : 'carried',
                'created_at' => now(), 'updated_at' => now()]);
            $count++;
        });

        return $count;
    }

    /** Compare a day's provider settlements with our paid online orders; differences become issues. */
    public function reconcile(string $date): int
    {
        $theirs = collect($this->gateway->settlements($date))->keyBy('reference');
        $ours = DB::table('com_orders')->where('channel', 'online')->where('provider', $this->gateway->name())->whereIn('status', ['paid', 'part_refunded', 'refunded'])
            ->whereDate('paid_at', $date)->get()->keyBy('provider_ref');
        $issues = [];
        foreach ($ours as $ref => $o) {
            $t = $theirs->get($ref);
            if ($t === null) {
                $issues[] = ['provider_ref' => (string) $ref, 'issue' => 'missing_at_provider', 'ours_cents' => (int) $o->total_cents, 'theirs_cents' => null];
            } elseif ((int) $t['amount'] !== (int) $o->total_cents) {
                $issues[] = ['provider_ref' => (string) $ref, 'issue' => 'amount_differs', 'ours_cents' => (int) $o->total_cents, 'theirs_cents' => (int) $t['amount']];
            }
        }
        foreach ($theirs as $ref => $t) {
            if (! $ours->has($ref)) {
                $issues[] = ['provider_ref' => (string) $ref, 'issue' => 'missing_in_ledger', 'ours_cents' => null, 'theirs_cents' => (int) $t['amount']];
            }
        }
        foreach ($issues as $issue) {
            DB::table('com_reconciliation_issues')->insert([...$issue, 'settlement_date' => $date, 'created_at' => now()]);
        }

        return count($issues);
    }
}
