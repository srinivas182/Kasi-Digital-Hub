<?php

declare(strict_types=1);

namespace Modules\Biz\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\ProductStock;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * Stock (ADR-037): items with a reorder level and a full history of movements. An item linked to a KasiMarket product
 * shares KasiMarket's quantity (the single source of truth) through the core ProductStock contract. "Bought" can also
 * write the cash book entry. Low stock is surfaced first and announced at most once a day.
 */
final readonly class Stock
{
    public const UNITS = ['each', 'kg', 'litre', 'pack', 'metre'];

    public const OUT_KINDS = ['sold', 'used', 'damaged', 'lost'];

    public function __construct(private ProductStock $products, private Books $books, private AuditLogger $audit) {}

    /** @param array{name: string, unit?: string, cost_cents?: int, price_cents?: int|null, quantity?: int, reorder_level?: int, market_product_id?: string|null} $data */
    public function add(string $businessId, User $by, array $data): int
    {
        $linked = $data['market_product_id'] ?? null;
        if ($linked !== null && $this->products->quantity($businessId, $linked) === null) {
            throw new DomainException(__('biz.stock.not_linked'));
        }
        $id = (int) DB::table('biz_stock_items')->insertGetId([
            'business_id' => $businessId, 'name' => mb_substr(trim($data['name']), 0, 80), 'unit' => in_array($data['unit'] ?? 'each', self::UNITS, true) ? ($data['unit'] ?? 'each') : 'each',
            'cost_cents' => max(0, (int) ($data['cost_cents'] ?? 0)), 'price_cents' => $data['price_cents'] ?? null,
            'quantity' => $linked !== null ? 0 : max(0, (int) ($data['quantity'] ?? 0)), 'reorder_level' => max(0, (int) ($data['reorder_level'] ?? 0)),
            'market_product_id' => $linked, 'archived' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record('biz.stock_item_added', meta: ['item' => $id], actor: $by);

        return $id;
    }

    /** The quantity: KasiMarket's for linked items, otherwise the item's own. */
    public function quantity(\stdClass $item): int
    {
        if ($item->market_product_id !== null) {
            return (int) ($this->products->quantity((string) $item->business_id, (string) $item->market_product_id) ?? 0);
        }

        return (int) $item->quantity;
    }

    /**
     * Record a movement. "bought" adds; sold/used/damaged/lost take away; "counted" sets the real number and records
     * the difference. A purchase can also go into the cash book (money out, stock and materials).
     */
    public function move(string $businessId, int $itemId, User $by, string $kind, int $quantity, ?int $costCents = null, ?string $note = null, bool $toBooks = false, ?User $subject = null): int
    {
        $item = DB::table('biz_stock_items')->where('id', $itemId)->where('business_id', $businessId)->first();
        if ($item === null || ! in_array($kind, ['bought', 'counted', ...self::OUT_KINDS], true) || $quantity < 0) {
            throw new DomainException(__('biz.stock.invalid'));
        }
        $before = $this->quantity($item);
        $change = match ($kind) {
            'bought' => $quantity,
            'counted' => $quantity - $before,
            default => -min($quantity, $before),
        };
        $after = DB::transaction(function () use ($item, $change, $kind, $costCents, $note, $by, $before): int {
            $after = $item->market_product_id !== null
                ? $this->products->adjust((string) $item->business_id, (string) $item->market_product_id, $change)
                : max(0, $before + $change);
            if ($item->market_product_id === null) {
                DB::table('biz_stock_items')->where('id', $item->id)->update(['quantity' => $after, 'updated_at' => now()]);
            }
            DB::table('biz_stock_moves')->insert(['item_id' => $item->id, 'kind' => $kind, 'change' => $change, 'after' => $after, 'cost_cents' => $costCents,
                'note' => $note !== null ? mb_substr(trim($note), 0, 200) : null, 'by' => $by->id, 'created_at' => now()]);

            return $after;
        });
        if ($kind === 'bought' && $costCents !== null && $costCents > 0) {
            DB::table('biz_stock_items')->where('id', $item->id)->update(['cost_cents' => intdiv($costCents, max(1, $quantity))]);
            if ($toBooks) {
                $this->books->record($businessId, $by, ['direction' => 'out', 'category' => 'stock_materials', 'amount_cents' => $costCents, 'method' => 'cash',
                    'note' => __('biz.stock.bought_note', ['quantity' => $quantity, 'item' => (string) $item->name])], null, 'manual', null, $subject);
            }
        }
        $this->audit->record('biz.stock_moved', meta: ['item' => $item->id, 'kind' => $kind, 'change' => $change], actor: $by);

        return $after;
    }

    /**
     * Items with their quantity, value and low flag, low ones first.
     *
     * @return list<array{id: int, name: string, unit: string, quantity: int, reorder_level: int, cost_cents: int, value_cents: int, low: bool, linked: bool}>
     */
    public function items(string $businessId): array
    {
        $rows = [];
        foreach (DB::table('biz_stock_items')->where('business_id', $businessId)->where('archived', false)->orderBy('name')->get() as $item) {
            $q = $this->quantity($item);
            $rows[] = ['id' => (int) $item->id, 'name' => (string) $item->name, 'unit' => (string) $item->unit, 'quantity' => $q, 'reorder_level' => (int) $item->reorder_level,
                'cost_cents' => (int) $item->cost_cents, 'value_cents' => $q * (int) $item->cost_cents, 'low' => (int) $item->reorder_level > 0 && $q <= (int) $item->reorder_level,
                'linked' => $item->market_product_id !== null];
        }
        usort($rows, static fn (array $a, array $b): int => [$b['low'], $a['name']] <=> [$a['low'], $b['name']]);

        return $rows;
    }

    /** @return list<array{kind: string, change: int, after: int, note: string|null, at: string}> */
    public function history(string $businessId, int $itemId): array
    {
        abort_unless(DB::table('biz_stock_items')->where('id', $itemId)->where('business_id', $businessId)->exists(), 404);

        return array_values(DB::table('biz_stock_moves')->where('item_id', $itemId)->latest('created_at')->limit(100)->get()
            ->map(static fn (object $m): array => ['kind' => (string) $m->kind, 'change' => (int) $m->change, 'after' => (int) $m->after,
                'note' => $m->note !== null ? (string) $m->note : null, 'at' => (string) $m->created_at])->all());
    }

    /**
     * Low items not yet announced today, marked as announced (one message a day per business).
     *
     * @return list<string> item names
     */
    public function takeNewLow(string $businessId): array
    {
        $low = array_values(array_filter($this->items($businessId), static fn (array $i): bool => $i['low']));
        $names = [];
        foreach ($low as $i) {
            $updated = DB::table('biz_stock_items')->where('id', $i['id'])->where(fn ($q) => $q->whereNull('low_notified_on')->orWhere('low_notified_on', '<', now()->startOfDay()))
                ->update(['low_notified_on' => now()]);
            if ($updated > 0) {
                $names[] = $i['name'];
            }
        }

        return $names;
    }
}
