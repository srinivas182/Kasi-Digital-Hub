<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commercial\Models\LedgerEntry;
use Modules\Commercial\Models\Product;
use Modules\Commercial\Notifications\CommercialNotification;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Hub package invoices (the franchise fee, Kasi is merchant of record), funder licence invoices with a
 * delivery fee for the hub that delivers, reminders at 7 and 14 days, and blocking new paid sales at 30
 * days. Youth services are never affected (ADR-027).
 */
final readonly class Invoicing
{
    public const BLOCK_AFTER_DAYS = 30;

    public function __construct(private Catalogue $catalogue, private Notifier $notifier) {}

    /** Monthly package invoices for live hubs (idempotent per hub and month). */
    public function packages(string $period): int
    {
        $count = 0;
        Hub::query()->where('status', 'live')->get()->each(function (Hub $hub) use ($period, &$count): void {
            $product = Product::query()->where('key', 'package_'.$hub->package)->first();
            $band = $product !== null ? $this->catalogue->price($product, Catalogue::tier($hub)) : null;
            if ($band === null || DB::table('com_invoices')->where(['kind' => 'package', 'hub_id' => $hub->id, 'period' => $period])->exists()) {
                return;
            }
            $vat = Money::vat($band['price'], (bool) config('kasi.commercial.kasi_vat_registered'));
            DB::table('com_invoices')->insert(['id' => (string) Str::ulid(), 'number' => self::number('INV'), 'kind' => 'package', 'hub_id' => $hub->id, 'period' => $period,
                'description' => 'Hub package: '.ucfirst($hub->package).' - '.$period, 'amount_cents' => $band['price'], 'vat_cents' => $vat['vat'],
                'due_on' => now()->addDays(14)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            $this->tellHub($hub, 'invoice', ['period' => $period, 'amount' => Money::format($band['price'])]);
            $count++;
        });

        return $count;
    }

    /** A funder programme licence (national account); the delivering hub earns a delivery fee. */
    public function licence(string $organisationId, ?Hub $deliveringHub, string $period, string $description, int $amountCents, int $deliveryFeeCents): string
    {
        $id = (string) Str::ulid();
        $vat = Money::vat($amountCents, (bool) config('kasi.commercial.kasi_vat_registered'));
        DB::table('com_invoices')->insert(['id' => $id, 'number' => self::number('LIC'), 'kind' => 'licence', 'hub_id' => $deliveringHub?->id, 'organisation_id' => $organisationId,
            'period' => $period, 'description' => $description, 'amount_cents' => $amountCents, 'vat_cents' => $vat['vat'], 'delivery_fee_cents' => $deliveryFeeCents,
            'due_on' => now()->addDays(30)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function markPaid(string $invoiceId, User $by): void
    {
        $inv = DB::table('com_invoices')->where('id', $invoiceId)->where('status', 'open')->first();
        if ($inv === null) {
            return;
        }
        DB::table('com_invoices')->where('id', $invoiceId)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
        $net = (int) $inv->amount_cents - (int) $inv->vat_cents;
        $entries = [['kasi', $net - (int) $inv->delivery_fee_cents], ['vat', (int) $inv->vat_cents]];
        if ((int) $inv->delivery_fee_cents > 0) {
            $entries[] = ['hub', (int) $inv->delivery_fee_cents]; // owed to the delivering hub, paid with its next payout
        }
        foreach ($entries as [$account, $amount]) {
            if ($amount !== 0) {
                LedgerEntry::query()->create(['invoice_id' => $invoiceId, 'hub_id' => $inv->hub_id, 'kind' => $inv->kind === 'package' ? 'package' : 'sale', 'account' => $account,
                    'amount_cents' => $amount, 'memo' => $inv->number, 'created_at' => now()]);
            }
        }
        if ($inv->kind === 'package' && $inv->hub_id !== null && ! DB::table('com_invoices')->where('kind', 'package')->where('hub_id', $inv->hub_id)->where('status', 'open')
            ->where('due_on', '<', now()->subDays(self::BLOCK_AFTER_DAYS - 14)->toDateString())->exists()) {
            HubFinance::upsert((string) $inv->hub_id, ['sales_blocked' => false]);
        }
        app(AuditLogger::class)->record('commercial.invoice_paid', meta: ['invoice' => $invoiceId], actor: $by);
    }

    /**
     * Reminders at 7 and 14 days after issue; at 30 days new paid sales are blocked.
     *
     * @return array{reminders: int, blocked: int}
     */
    public function chase(): array
    {
        $done = ['reminders' => 0, 'blocked' => 0];
        DB::table('com_invoices')->where('kind', 'package')->where('status', 'open')->get()->each(function (object $inv) use (&$done): void {
            $age = (int) CarbonImmutable::parse((string) $inv->created_at)->diffInDays(now());
            $hub = Hub::query()->find($inv->hub_id);
            if (! $hub instanceof Hub) {
                return;
            }
            $due = $age >= 14 ? 2 : ($age >= 7 ? 1 : 0);
            if ($due > (int) $inv->reminders) {
                DB::table('com_invoices')->where('id', $inv->id)->update(['reminders' => $due]);
                $this->tellHub($hub, 'invoice_reminder', ['number' => (string) $inv->number, 'amount' => Money::format((int) $inv->amount_cents)]);
                $done['reminders']++;
            }
            if ($age >= self::BLOCK_AFTER_DAYS && ! (bool) DB::table('com_hub_finance')->where('hub_id', $hub->id)->value('sales_blocked')) {
                HubFinance::upsert($hub->id, ['sales_blocked' => true]);
                $this->tellHub($hub, 'sales_blocked', ['number' => (string) $inv->number]);
                $done['blocked']++;
            }
        });

        return $done;
    }

    /** @param array<string, string> $params */
    private function tellHub(Hub $hub, string $kind, array $params): void
    {
        $owners = RoleAssignment::query()->whereIn('role', ['hub_owner', 'hub_manager'])->where('scope_type', 'hub')->where('scope_id', $hub->id)->pluck('user_id');
        User::query()->whereIn('id', $owners)->get()->each(fn (User $u) => $this->notifier->send($u, new CommercialNotification($kind, $params, '/commercial/hub')));
    }

    private static function number(string $prefix): string
    {
        $year = now()->format('Y');
        $n = DB::table('com_invoices')->where('number', 'like', "{$prefix}-{$year}-%")->count() + 1;
        do {
            $number = sprintf('%s-%s-%05d', $prefix, $year, $n++);
        } while (DB::table('com_invoices')->where('number', $number)->exists());

        return $number;
    }
}
