<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Core\Safety\ProhibitedItems;
use Modules\Market\Models\Product;

/**
 * Listing safety (ADR-032): a prohibited-items list (clear breaches refused with a reason), the platform
 * moderation checks (unclear ones held for review in the review inbox), and review of a shop's first 3 products.
 * The list is a placeholder until Ku Tirhisana confirms it.
 */
final readonly class Safety
{
    public const FIRST_REVIEWED = 3;

    /** term => reason key; the list now lives in Core (S31). */
    public const PROHIBITED = ProhibitedItems::LIST;

    public function __construct(private ModerationService $moderation) {}

    /** Reason key when the text clearly names a prohibited item, else null. */
    public function prohibited(string $text): ?string
    {
        return ProhibitedItems::reason($text);
    }

    /**
     * Decide what happens on "publish": refused (prohibited), held for review (first products or moderation
     * flag), or published.
     *
     * @return array{status: 'refused'|'review'|'published', reason: string|null}
     */
    public function check(Product $product, User $by): array
    {
        $text = $product->title.' '.$product->description;
        if (($reason = $this->prohibited($text)) !== null) {
            return ['status' => 'refused', 'reason' => 'market.prohibited.'.$reason];
        }
        $first = $product->shop->approved_products < self::FIRST_REVIEWED;
        $result = $this->moderation->check($text, 'market_product', $product->id, $by, useAi: true,
            extraReasons: $first ? [__('market.review.first_products')] : []);
        if ($result['verdict'] === 'flag') {
            return ['status' => 'review', 'reason' => $first ? 'market.review.first_products' : 'market.review.check'];
        }

        return ['status' => 'published', 'reason' => null];
    }
}
