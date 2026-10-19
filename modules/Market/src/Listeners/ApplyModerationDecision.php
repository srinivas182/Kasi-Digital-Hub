<?php

declare(strict_types=1);

namespace Modules\Market\Listeners;

use Modules\Core\Events\ModerationDecided;
use Modules\Core\Identity\Models\User;
use Modules\Market\Models\Product;
use Modules\Market\Services\Products;

/** A reviewer's decision on a held product (review inbox / moderation screen) publishes or refuses it. */
final readonly class ApplyModerationDecision
{
    public function __construct(private Products $products) {}

    public function handle(ModerationDecided $event): void
    {
        if ($event->subjectType !== 'market_product') {
            return;
        }
        $product = Product::query()->with('shop')->find($event->subjectId);
        if (! $product instanceof Product || $product->status !== 'review') {
            return;
        }
        if ($event->decision === 'approved') {
            $product->forceFill(['status' => 'published', 'review_reason' => null, 'published_at' => $product->published_at ?? now()])->save();
            $this->products->live($product, User::query()->find($event->by));
        } else {
            $product->forceFill(['status' => 'refused', 'review_reason' => mb_substr($event->reason, 0, 200)])->save();
        }
    }
}
