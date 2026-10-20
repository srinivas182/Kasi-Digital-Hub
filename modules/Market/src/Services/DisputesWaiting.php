<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Insights\ReviewSource;

/** Unresolved KasiMarket disputes in the KasiHub review inbox (S27). */
final class DisputesWaiting implements ReviewSource
{
    public function waiting(): array
    {
        return array_values(DB::table('market_disputes')->join('market_orders', 'market_orders.id', '=', 'market_disputes.order_id')
            ->whereIn('market_disputes.status', ['open', 'responded'])->orderBy('market_disputes.created_at')->limit(200)
            ->get(['market_disputes.id', 'market_disputes.reason', 'market_disputes.status', 'market_disputes.respond_by', 'market_disputes.created_at', 'market_orders.number'])
            ->map(static fn (object $d): array => [
                'type' => 'market_dispute', 'id' => (string) $d->id, 'title' => __('market.dispute.inbox_title', ['number' => $d->number]),
                'reason' => __('market.dispute.reason.'.$d->reason).($d->status === 'open' && now()->gt($d->respond_by) ? ' · '.__('market.dispute.trader_late') : ''),
                'since' => (string) $d->created_at, 'url' => '/market/disputes/'.$d->id, 'bulk' => false,
            ])->all());
    }
}
