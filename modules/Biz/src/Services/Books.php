<?php

declare(strict_types=1);

namespace Modules\Biz\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Biz\Events\EntryRecorded;
use Modules\Biz\Models\Entry;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Enterprise\BusinessTransactions;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * The cash book (ADR-036): money in and out per KasiStart business. Profit counts trading money only - loans and the
 * owner's own money change the cash balance but not profit. Entries can be corrected or removed for 30 days, and
 * every change is kept. Other portals' money (e.g. KasiMarket sales) is imported once by reference.
 */
final readonly class Books
{
    public const CATEGORIES = [
        'in' => ['sales', 'services', 'other_income', 'loan_received', 'owner_put_in'],
        'out' => ['stock_materials', 'transport', 'rent', 'airtime_data', 'wages', 'equipment', 'fees_charges', 'refunds', 'loan_repayment', 'owner_took_out', 'other_expense'],
    ];

    /** Money that is not trading: in the cash balance, not in profit. */
    public const NOT_TRADING = ['loan_received', 'owner_put_in', 'loan_repayment', 'owner_took_out'];

    public const METHODS = ['cash', 'card', 'mobile'];

    public const CORRECT_DAYS = 30;

    public function __construct(private AuditLogger $audit, private DocumentVault $vault) {}

    public function open(string $businessId): void
    {
        DB::table('biz_books')->insertOrIgnore(['business_id' => $businessId, 'opening' => json_encode(['cash' => 0, 'card' => 0, 'mobile' => 0]),
            'started_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, int> $opening cents per method */
    public function setOpening(string $businessId, array $opening, User $by): void
    {
        $this->open($businessId);
        DB::table('biz_books')->where('business_id', $businessId)->update(['opening' => json_encode(array_intersect_key(array_map('intval', $opening), array_flip(self::METHODS))),
            'updated_at' => now()]);
        $this->audit->record('biz.opening_set', meta: ['business' => $businessId], actor: $by);
    }

    /** @param array{direction: string, amount_cents: int, category: string, method: string, occurred_on?: string|null, note?: string|null, contact_id?: int|null} $data */
    public function record(string $businessId, User $by, array $data, ?UploadedFile $receipt = null, string $source = 'manual', ?string $ref = null, ?User $subject = null): Entry
    {
        $this->validate($data);
        $this->open($businessId);
        $receiptId = $receipt !== null ? $this->vault->store($subject ?? $by, $receipt, 'receipt')->id : null;
        $entry = Entry::query()->create([
            'business_id' => $businessId, 'direction' => $data['direction'], 'amount_cents' => $data['amount_cents'], 'category' => $data['category'], 'method' => $data['method'],
            'occurred_on' => $data['occurred_on'] ?? now()->toDateString(), 'note' => isset($data['note']) ? mb_substr(trim((string) $data['note']), 0, 300) : null,
            'contact_id' => $data['contact_id'] ?? null, 'receipt_document_id' => $receiptId, 'source' => $source, 'source_ref' => $ref, 'hidden' => false, 'created_by' => $by->id,
        ]);
        event(new EntryRecorded($subject ?? $by, $subject !== null ? $by->id : null, ['business' => $businessId, 'direction' => $entry->direction]));

        return $entry;
    }

    /** @param array{direction: string, amount_cents: int, category: string, method: string, occurred_on?: string|null, note?: string|null, contact_id?: int|null} $data */
    public function edit(Entry $entry, User $by, array $data): void
    {
        $this->guardChange($entry);
        $this->validate($data);
        $this->history($entry, 'edited', $by);
        $entry->fill(array_intersect_key($data, array_flip(['direction', 'amount_cents', 'category', 'method', 'occurred_on', 'note', 'contact_id'])))->save();
    }

    public function remove(Entry $entry, User $by): void
    {
        $this->guardChange($entry);
        $this->history($entry, 'removed', $by);
        $entry->delete();
    }

    /** Imported entries (e.g. from KasiMarket) can be hidden from the books, never edited. */
    public function hide(Entry $entry, User $by, bool $hidden): void
    {
        $this->history($entry, 'hidden', $by);
        $entry->forceFill(['hidden' => $hidden])->save();
    }

    /** Bring in money recorded by other portals since the last sync (idempotent; refund totals are updated). */
    public function sync(string $businessId, User $by): int
    {
        $this->open($businessId);
        $book = DB::table('biz_books')->where('business_id', $businessId)->first();
        $from = $book?->synced_at !== null ? CarbonImmutable::parse((string) $book->synced_at)->subDay() : now()->subYear()->toImmutable();
        $added = 0;
        foreach (app()->tagged(BusinessTransactions::TAG) as $source) {
            if (! $source instanceof BusinessTransactions) {
                continue;
            }
            foreach ($source->since($businessId, $from) as $t) {
                $existing = Entry::withTrashed()->where('business_id', $businessId)->where('source_ref', $t['ref'])->first();
                if ($existing instanceof Entry) {
                    if ($existing->amount_cents !== $t['amount_cents'] && ! $existing->trashed()) {
                        $this->history($existing, 'edited', $by);
                        $existing->forceFill(['amount_cents' => $t['amount_cents']])->save();
                    }

                    continue;
                }
                Entry::query()->create(['business_id' => $businessId, 'direction' => $t['direction'], 'amount_cents' => $t['amount_cents'], 'category' => $t['category'],
                    'method' => in_array($t['method'], self::METHODS, true) ? $t['method'] : 'cash', 'occurred_on' => $t['date']->toDateString(), 'note' => mb_substr($t['note'], 0, 300),
                    'source' => explode(':', $t['ref'])[0], 'source_ref' => $t['ref'], 'hidden' => false, 'created_by' => null]);
                $added++;
            }
        }
        DB::table('biz_books')->where('business_id', $businessId)->update(['synced_at' => now(), 'updated_at' => now()]);

        return $added;
    }

    /**
     * One month: trading money in and out, profit, cash on hand per method at month end, biggest costs, and the
     * same figures for the month before.
     *
     * @return array{month: string, in: int, out: int, profit: int, cash: array<string, int>, top_costs: list<array{category: string, cents: int}>, previous_profit: int}
     */
    public function summary(string $businessId, string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $trading = fn (CarbonImmutable $a, CarbonImmutable $b, string $dir): int => (int) $this->visible($businessId)->where('direction', $dir)
            ->whereNotIn('category', self::NOT_TRADING)->whereBetween('occurred_on', [$a->toDateString(), $b->toDateString()])->sum('amount_cents');
        $in = $trading($start, $end, 'in');
        $out = $trading($start, $end, 'out');
        $prevStart = $start->subMonthNoOverflow();
        $book = DB::table('biz_books')->where('business_id', $businessId)->first();
        $opening = (array) json_decode((string) ($book->opening ?? '{}'), true);
        $cash = [];
        foreach (self::METHODS as $m) {
            $moves = $this->visible($businessId)->where('method', $m)->where('occurred_on', '<=', $end->toDateString())
                ->selectRaw("sum(case when direction = 'in' then amount_cents else -amount_cents end) as net")->value('net');
            $cash[$m] = (int) ($opening[$m] ?? 0) + (int) $moves;
        }
        $top = $this->visible($businessId)->where('direction', 'out')->whereNotIn('category', self::NOT_TRADING)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])->groupBy('category')
            ->orderByDesc(DB::raw('sum(amount_cents)'))->limit(4)->toBase()->get(['category', DB::raw('sum(amount_cents) as c')]);

        return ['month' => $start->format('Y-m'), 'in' => $in, 'out' => $out, 'profit' => $in - $out, 'cash' => $cash,
            'top_costs' => array_values($top->map(static fn (object $r): array => ['category' => (string) $r->category, 'cents' => (int) $r->c])->all()),
            'previous_profit' => $trading($prevStart, $prevStart->endOfMonth(), 'in') - $trading($prevStart, $prevStart->endOfMonth(), 'out')];
    }

    /** @return Builder<Entry> */
    public function visible(string $businessId): Builder
    {
        return Entry::query()->where('business_id', $businessId)->where('hidden', false);
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        $direction = (string) ($data['direction'] ?? '');
        if (! isset(self::CATEGORIES[$direction]) || ! in_array($data['category'] ?? null, self::CATEGORIES[$direction], true)
            || ! in_array($data['method'] ?? null, self::METHODS, true) || (int) ($data['amount_cents'] ?? 0) <= 0) {
            throw new DomainException(__('biz.entry.invalid'));
        }
    }

    private function guardChange(Entry $entry): void
    {
        if ($entry->source !== 'manual' && $entry->source !== 'credit') {
            throw new DomainException(__('biz.entry.imported'));
        }
        if ($entry->created_at->lt(now()->subDays(self::CORRECT_DAYS))) {
            throw new DomainException(__('biz.entry.too_old'));
        }
    }

    private function history(Entry $entry, string $action, User $by): void
    {
        DB::table('biz_entry_changes')->insert(['entry_id' => $entry->id, 'action' => $action,
            'before' => json_encode(array_intersect_key($entry->getAttributes(), array_flip(['direction', 'amount_cents', 'category', 'method', 'occurred_on', 'note', 'hidden']))),
            'changed_by' => $by->id, 'created_at' => now()]);
        $this->audit->record('biz.entry_'.$action, meta: ['entry' => $entry->id], actor: $by);
    }
}
