<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Commercial\Models\Product;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * National price list with metro/rural tiers, floors and ceilings, and versioned split rules; every
 * change goes through four-eyes approval.
 */
final readonly class Catalogue
{
    public function __construct(private Approvals $approvals) {}

    public static function tier(?Hub $hub): string
    {
        if ($hub === null) {
            return 'metro';
        }
        $category = DB::table('municipalities')->where('id', $hub->municipality_id)->value('category');

        return $category === 'metro' ? 'metro' : 'rural';
    }

    /** @return array{price: int, floor: int, ceiling: int, id: int}|null */
    public function price(Product $product, string $tier): ?array
    {
        $row = DB::table('com_prices')->where('product_id', $product->id)->where('tier', $tier)->where('status', 'active')
            ->where('effective_from', '<=', now()->toDateString())->orderByDesc('effective_from')->orderByDesc('id')->first();

        return $row === null ? null : ['price' => (int) $row->price_cents, 'floor' => (int) $row->floor_cents, 'ceiling' => (int) $row->ceiling_cents, 'id' => (int) $row->id];
    }

    public function proposePrice(Product $product, string $tier, int $price, int $floor, int $ceiling, string $effectiveFrom, User $by): int
    {
        if (! ($floor <= $price && $price <= $ceiling)) {
            throw new DomainException(__('commercial.price.band'));
        }
        $id = (int) DB::table('com_prices')->insertGetId(['product_id' => $product->id, 'tier' => $tier, 'price_cents' => $price, 'floor_cents' => $floor,
            'ceiling_cents' => $ceiling, 'effective_from' => $effectiveFrom, 'proposed_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->approvals->request('price', (string) $id, ['product' => $product->key, 'tier' => $tier, 'price' => $price], $by);

        return $id;
    }

    /** @return array{id: int, kasi_bp: int, hub_bp: int, partner_bp: int}|null */
    public function rule(string $stream): ?array
    {
        $r = DB::table('com_split_rules')->where('stream', $stream)->where('status', 'active')->where('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')->orderByDesc('id')->first();

        return $r === null ? null : ['id' => (int) $r->id, 'kasi_bp' => (int) $r->kasi_bp, 'hub_bp' => (int) $r->hub_bp, 'partner_bp' => (int) $r->partner_bp];
    }

    public function proposeRule(string $stream, int $kasiBp, int $hubBp, int $partnerBp, string $effectiveFrom, ?string $note, User $by): int
    {
        if ($kasiBp + $hubBp + $partnerBp !== 10000) {
            throw new DomainException(__('commercial.split.total'));
        }
        $id = (int) DB::table('com_split_rules')->insertGetId(['stream' => $stream, 'kasi_bp' => $kasiBp, 'hub_bp' => $hubBp, 'partner_bp' => $partnerBp,
            'effective_from' => $effectiveFrom, 'note' => $note, 'proposed_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->approvals->request('split_rule', (string) $id, ['stream' => $stream, 'kasi' => $kasiBp, 'hub' => $hubBp, 'partner' => $partnerBp], $by);

        return $id;
    }

    /** Apply an approved (or rejected) price / rule decision. */
    public function decide(int $approvalId, User $by, bool $approve, ?string $reason = null): string
    {
        $decision = $this->approvals->decide($approvalId, $by, $approve, $reason);
        $table = match ($decision['kind']) {
            'price' => 'com_prices',
            'split_rule' => 'com_split_rules',
            default => null,
        };
        if ($table !== null) {
            DB::table($table)->where('id', (int) $decision['subject_id'])->update(['status' => $approve ? 'active' : 'rejected', 'approved_by' => $by->id, 'updated_at' => now()]);
        }

        return $decision['kind'];
    }
}
