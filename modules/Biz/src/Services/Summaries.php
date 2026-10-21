<?php

declare(strict_types=1);

namespace Modules\Biz\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Enterprise\Signals;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * A summary the owner chooses to share (ADR-037): bands and counts only - never exact amounts. Shared by a 7-day link
 * the owner can withdraw; every view is logged. The owner sees exactly what the recipient will see.
 */
final readonly class Summaries
{
    public const LINK_DAYS = 7;

    public function __construct(private Books $books, private Stock $stock, private AuditLogger $audit) {}

    /**
     * @return array{business: string, months_recorded: int, turnover_band: string|null, profitable_months: int, of_months: int,
     *     stock_band: string|null, compliance_up_to_date: bool, overdue: list<string>, market_rating: float|null, as_of: string}
     */
    public function build(string $businessId, string $businessName): array
    {
        $months = 0;
        $profitable = 0;
        for ($i = 1; $i <= 6; $i++) {
            $month = now()->subMonthsNoOverflow($i)->format('Y-m');
            $s = $this->books->summary($businessId, $month);
            if ($s['in'] > 0 || $s['out'] > 0) {
                $months++;
                $profitable += $s['profit'] > 0 ? 1 : 0;
            }
        }
        $signals = Signals::for($businessId);
        $stockValue = array_sum(array_column($this->stock->items($businessId), 'value_cents'));

        return [
            'business' => $businessName, 'months_recorded' => $months, 'turnover_band' => is_string($signals['biz.turnover_band'] ?? null) ? $signals['biz.turnover_band'] : null,
            'profitable_months' => $profitable, 'of_months' => 6,
            'stock_band' => $stockValue <= 0 ? null : ($stockValue < 100000 ? 'under_1k' : ($stockValue < 1000000 ? '1k_10k' : ($stockValue < 5000000 ? '10k_50k' : '50k_plus'))),
            'compliance_up_to_date' => Compliance::upToDate($businessId),
            'overdue' => array_values(DB::table('biz_obligations')->where('business_id', $businessId)->where('status', 'due')->where('due_on', '<', now()->toDateString())
                ->distinct()->pluck('rule')->map(static fn ($r): string => (string) $r)->all()),
            'market_rating' => is_numeric($signals['market.rating'] ?? null) ? (float) $signals['market.rating'] : null,
            'as_of' => now()->toDateString(),
        ];
    }

    /** Create a 7-day link; the token is returned once and only its hash is kept. */
    public function share(string $businessId, User $by, ?string $recipient): string
    {
        $token = Str::random(40);
        DB::table('biz_shares')->insert(['id' => (string) Str::ulid(), 'business_id' => $businessId, 'token_hash' => hash('sha256', $token),
            'recipient' => $recipient !== null ? mb_substr(trim($recipient), 0, 120) : null, 'expires_at' => now()->addDays(self::LINK_DAYS), 'created_by' => $by->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('biz.summary_shared', meta: ['business' => $businessId, 'recipient' => $recipient], actor: $by);

        return $token;
    }

    public function withdraw(string $businessId, string $shareId, User $by): void
    {
        $updated = DB::table('biz_shares')->where('id', $shareId)->where('business_id', $businessId)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        if ($updated === 0) {
            throw new DomainException(__('biz.share.not_found'));
        }
        $this->audit->record('biz.summary_withdrawn', meta: ['share' => $shareId], actor: $by);
    }

    /** Open a shared summary by its token (logged); null when unknown, expired or withdrawn. */
    public function open(string $token, ?User $viewer, ?string $ip): ?string
    {
        $share = DB::table('biz_shares')->where('token_hash', hash('sha256', $token))->first();
        if ($share === null || $share->revoked_at !== null || now()->gt($share->expires_at)) {
            return null;
        }
        DB::table('biz_share_views')->insert(['share_id' => $share->id, 'viewer_id' => $viewer?->id, 'ip_hash' => $ip !== null ? hash('sha256', $ip.config('app.key')) : null, 'created_at' => now()]);

        return (string) $share->business_id;
    }

    /** @return list<array{id: string, recipient: string|null, expires: string, withdrawn: bool, views: int}> */
    public function shares(string $businessId): array
    {
        return array_values(DB::table('biz_shares')->where('business_id', $businessId)->latest('created_at')->limit(20)->get()
            ->map(static fn (object $s): array => ['id' => (string) $s->id, 'recipient' => $s->recipient !== null ? (string) $s->recipient : null, 'expires' => (string) $s->expires_at,
                'withdrawn' => $s->revoked_at !== null, 'views' => DB::table('biz_share_views')->where('share_id', $s->id)->count()])->all());
    }
}
