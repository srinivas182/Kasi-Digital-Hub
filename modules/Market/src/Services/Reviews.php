<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Market\Events\ReviewAdded;
use Modules\Market\Models\Order;

/**
 * Reviews (ADR-035): only the buyer of a completed order, within 30 days, one per order; moderated; one public trader
 * reply; a shop's rating is shown only from 3 reviews.
 */
final readonly class Reviews
{
    public const DAYS = 30;

    public const MIN_FOR_RATING = 3;

    public function __construct(private ModerationService $moderation, private AuditLogger $audit) {}

    public function canReview(Order $order, User $buyer): bool
    {
        return $order->buyer_id === $buyer->id && $order->status === 'completed' && $order->getAttribute('payment_status') !== 'refunded'
            && $order->completed_at !== null && $order->completed_at->gte(now()->subDays(self::DAYS))
            && ! DB::table('market_reviews')->where('order_id', $order->id)->exists();
    }

    public function add(Order $order, User $buyer, int $rating, ?string $body): void
    {
        if (! $this->canReview($order, $buyer)) {
            throw new DomainException(__('market.review.not_allowed'));
        }
        $text = $body !== null ? mb_substr(trim($body), 0, 500) : null;
        $held = $text !== null && $text !== '' && $this->moderation->check($text, 'market_review', $order->id, $buyer, useAi: false)['verdict'] === 'flag';
        DB::table('market_reviews')->insert(['order_id' => $order->id, 'shop_id' => $order->shop_id, 'buyer_id' => $buyer->id, 'rating' => max(1, min(5, $rating)),
            'body' => $text, 'status' => $held ? 'held' : 'sent', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('market.review_added', meta: ['order' => $order->id, 'rating' => $rating], actor: $buyer);
        event(new ReviewAdded($buyer, null, ['order' => $order->id, 'shop' => $order->shop_id, 'rating' => $rating]));
    }

    public function reply(int $reviewId, User $trader, string $reply): void
    {
        $review = DB::table('market_reviews')->where('id', $reviewId)->first();
        if ($review === null || $review->reply !== null) {
            throw new DomainException(__('market.review.reply_once'));
        }
        $text = mb_substr(trim($reply), 0, 500);
        $held = $this->moderation->check($text, 'market_review', (string) $reviewId, $trader, useAi: false)['verdict'] === 'flag';
        DB::table('market_reviews')->where('id', $reviewId)->update(['reply' => $text, 'reply_status' => $held ? 'held' : 'sent', 'replied_at' => now(), 'updated_at' => now()]);
    }

    /** Average of visible reviews, or null below the minimum. */
    public static function rating(string $shopId): ?float
    {
        $row = DB::table('market_reviews')->where('shop_id', $shopId)->where('status', 'sent')->selectRaw('count(*) as n, avg(rating) as r')->first();

        return $row !== null && (int) $row->n >= self::MIN_FOR_RATING ? round((float) $row->r, 1) : null;
    }

    /** @return list<array{rating: int, body: string|null, by: string, at: string, reply: string|null}> */
    public static function shown(string $shopId, int $limit = 20): array
    {
        return array_values(DB::table('market_reviews')->join('users', 'users.id', '=', 'market_reviews.buyer_id')->where('market_reviews.shop_id', $shopId)
            ->where('market_reviews.status', 'sent')->latest('market_reviews.created_at')->limit($limit)
            ->get(['market_reviews.rating', 'market_reviews.body', 'market_reviews.reply', 'market_reviews.reply_status', 'market_reviews.created_at', 'users.first_name', 'users.last_name'])
            ->map(static fn (object $r): array => ['rating' => (int) $r->rating, 'body' => $r->body !== null ? (string) $r->body : null,
                // First name and initial only.
                'by' => trim((string) $r->first_name.' '.mb_substr((string) $r->last_name, 0, 1).'.'), 'at' => (string) $r->created_at,
                'reply' => $r->reply_status === 'sent' ? (string) $r->reply : null])->all());
    }
}
