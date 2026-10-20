<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Market\Models\Order;
use Modules\Market\Models\Shop;
use Modules\Market\Notifications\MarketNotification;

/**
 * Reliability and enforcement (ADR-035): the trader's private signals and the "Replies quickly" badge; buyer no-shows
 * (3 in 90 days -> online payment only for 90 days, private); strikes (3 in 180 days -> moderators may suspend).
 */
final readonly class Trust
{
    public const NO_SHOW_LIMIT = 3;

    public const NO_SHOW_DAYS = 90;

    public const STRIKE_LIMIT = 3;

    public const STRIKE_DAYS = 180;

    public function __construct(private AuditLogger $audit, private Notifier $notifier) {}

    /** @return array{orders: int, median_accept_hours: float|null, acceptance: int|null, lapsed: int, completion: int|null} */
    public static function signals(Shop $shop, int $days = 90): array
    {
        $orders = Order::query()->where('shop_id', $shop->id)->where('created_at', '>=', now()->subDays($days))->where('payment_status', '!=', 'pending')->get();
        $answered = $orders->whereNotIn('status', ['placed', 'lapsed', 'cancelled']);
        $hours = $orders->whereNotNull('accepted_at')->map(static fn (Order $o): float => $o->created_at->diffInMinutes($o->accepted_at) / 60)->sort()->values();
        $accepted = $orders->whereNotNull('accepted_at')->count();

        return [
            'orders' => $orders->count(),
            'median_accept_hours' => $hours->isEmpty() ? null : round((float) $hours->median(), 1),
            'acceptance' => $answered->count() > 0 ? (int) round(100 * $accepted / $answered->count()) : null,
            'lapsed' => $orders->where('status', 'lapsed')->count(),
            'completion' => $accepted > 0 ? (int) round(100 * $orders->where('status', 'completed')->count() / $accepted) : null,
        ];
    }

    public static function repliesQuickly(Shop $shop): bool
    {
        $s = self::signals($shop);

        return $s['orders'] >= 5 && $s['median_accept_hours'] !== null && $s['median_accept_hours'] <= 12;
    }

    /** The trader says the buyer didn't come for a cash order. */
    public function noShow(Order $order, User $trader): void
    {
        if ($order->getAttribute('payment') !== 'cash' || ! in_array($order->status, ['accepted', 'ready'], true)) {
            throw new DomainException(__('market.noshow.not_allowed'));
        }
        DB::table('market_no_shows')->insertOrIgnore(['order_id' => $order->id, 'buyer_id' => $order->buyer_id, 'shop_id' => $order->shop_id, 'created_at' => now()]);
        app(Ordering::class)->move($order, $trader, 'cancelled', __('market.noshow.reason'));
        if (self::onlineOnly($order->buyer_id)) {
            $buyer = User::query()->find($order->buyer_id);
            if ($buyer instanceof User) {
                $this->notifier->send($buyer, new MarketNotification('online_only', [], '/market/orders'));
            }
        }
    }

    /** After 3 no-shows in 90 days a buyer pays online for 90 days. */
    public static function onlineOnly(string $buyerId): bool
    {
        return DB::table('market_no_shows')->where('buyer_id', $buyerId)->where('created_at', '>=', now()->subDays(self::NO_SHOW_DAYS))->count() >= self::NO_SHOW_LIMIT;
    }

    public function strike(Shop $shop, User $moderator, string $reason, string $source): int
    {
        DB::table('market_strikes')->insert(['shop_id' => $shop->id, 'reason' => mb_substr($reason, 0, 300), 'source' => $source, 'given_by' => $moderator->id,
            'expires_at' => now()->addDays(self::STRIKE_DAYS), 'created_at' => now()]);
        $this->audit->record('market.strike', meta: ['shop' => $shop->id, 'reason' => $reason], actor: $moderator);
        $this->tellTrader($shop, 'strike', ['reason' => $reason]);

        return self::activeStrikes($shop);
    }

    public static function activeStrikes(Shop $shop): int
    {
        return DB::table('market_strikes')->where('shop_id', $shop->id)->where('expires_at', '>', now())->count();
    }

    /** Moderators suspend a shop with 3 active strikes: hidden, open orders cancelled (and refunded if paid). */
    public function suspend(Shop $shop, User $moderator, string $reason): void
    {
        if (self::activeStrikes($shop) < self::STRIKE_LIMIT) {
            throw new DomainException(__('market.enforce.not_enough'));
        }
        $shop->forceFill(['status' => 'suspended', 'suspended_reason' => mb_substr($reason, 0, 300), 'suspended_at' => now()])->save();
        foreach (Order::query()->where('shop_id', $shop->id)->whereIn('status', Order::OPEN)->get() as $order) {
            app(Ordering::class)->forceCancel($order, $moderator, __('market.enforce.order_cancelled'));
        }
        $this->audit->record('market.shop_suspended', meta: ['shop' => $shop->id, 'reason' => $reason], actor: $moderator);
        $this->tellTrader($shop, 'suspended', ['reason' => $reason]);
    }

    public function lift(Shop $shop, User $moderator, string $note): void
    {
        $shop->forceFill(['status' => 'open', 'suspended_reason' => null, 'suspended_at' => null])->save();
        $this->audit->record('market.shop_restored', meta: ['shop' => $shop->id, 'note' => $note], actor: $moderator);
        $this->tellTrader($shop, 'restored', []);
    }

    /** @param array<string, string> $params */
    private function tellTrader(Shop $shop, string $kind, array $params): void
    {
        $owner = User::query()->find($shop->opened_by);
        if ($owner instanceof User) {
            $this->notifier->send($owner, new MarketNotification($kind, $params, '/market/shop'));
        }
    }
}
