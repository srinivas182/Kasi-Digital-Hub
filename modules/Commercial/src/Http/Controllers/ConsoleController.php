<?php

declare(strict_types=1);

namespace Modules\Commercial\Http\Controllers;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Commercial\Models\Order;
use Modules\Commercial\Models\Product;
use Modules\Commercial\Services\Catalogue;
use Modules\Commercial\Services\HubFinance;
use Modules\Commercial\Services\Invoicing;
use Modules\Commercial\Services\Money;
use Modules\Commercial\Services\Orders;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * National commercial and finance console: revenue, league table, approvals (four-eyes), catalogue and
 * rules, hubs' readiness, invoices, refunds, reconciliation and ledger export.
 */
final class ConsoleController
{
    public function overview(Request $request): Response
    {
        $user = $this->user($request);
        $since = now()->startOfMonth();
        $paid = Order::query()->with('product')->whereIn('status', ['paid', 'part_refunded', 'refunded'])->where('paid_at', '>=', $since)->get();
        $byStream = $paid->groupBy(static fn (Order $o): string => $o->product->stream)->map(static fn ($g): array => [
            'sales' => Money::format((int) $g->sum('total_cents') - (int) $g->sum('refunded_cents')), 'kasi' => Money::format((int) $g->sum(static fn (Order $o): int => $o->shares['kasi'])),
        ]);
        $league = DB::table('com_orders')->join('hubs', 'hubs.id', '=', 'com_orders.hub_id')->whereIn('com_orders.status', ['paid', 'part_refunded'])->where('paid_at', '>=', $since)
            ->groupBy('hubs.name')->selectRaw('hubs.name as hub, SUM(total_cents) as total, COUNT(*) as orders')->orderByDesc('total')->limit(20)->get()
            ->map(static fn (object $r): array => ['hub' => (string) $r->hub, 'total' => Money::format((int) $r->total), 'orders' => (int) $r->orders]);

        return Inertia::render('Commercial/Console', [
            'can' => ['manage' => $this->may($user, 'commercial.manage'), 'finance' => $this->may($user, 'commercial.finance')],
            'byStream' => $byStream,
            'league' => $league,
            'approvals' => DB::table('com_approvals')->join('users', 'users.id', '=', 'com_approvals.requested_by')->where('com_approvals.status', 'pending')->orderBy('com_approvals.created_at')
                ->get(['com_approvals.id', 'kind', 'subject_id', 'change', 'requested_by', 'users.first_name'])
                ->map(static fn (object $a): array => ['id' => (int) $a->id, 'kind' => (string) $a->kind, 'change' => (array) json_decode((string) $a->change, true),
                    'by' => (string) $a->first_name, 'mine' => $a->requested_by === $user->id]),
            'overdue' => DB::table('com_invoices')->join('hubs', 'hubs.id', '=', 'com_invoices.hub_id')->where('com_invoices.status', 'open')->where('due_on', '<', now()->toDateString())
                ->get(['com_invoices.id', 'number', 'hubs.name', 'amount_cents', 'due_on'])
                ->map(static fn (object $i): array => ['id' => (string) $i->id, 'number' => (string) $i->number, 'hub' => (string) $i->name, 'amount' => Money::format((int) $i->amount_cents), 'due' => (string) $i->due_on]),
            'issues' => DB::table('com_reconciliation_issues')->whereNull('resolved_at')->orderByDesc('settlement_date')->limit(50)->get(),
            'hubs' => DB::table('hubs')->leftJoin('com_hub_finance', 'com_hub_finance.hub_id', '=', 'hubs.id')->orderBy('hubs.name')
                ->get(['hubs.id', 'hubs.name', 'hubs.package', 'company_registration', 'vat_registered', 'bank_verified', 'franchise_version', 'sales_blocked'])
                ->map(static fn (object $h): array => ['id' => (string) $h->id, 'name' => (string) $h->name, 'package' => (string) $h->package, 'company' => $h->company_registration !== null,
                    'bank' => (bool) $h->bank_verified, 'franchise' => $h->franchise_version === config('kasi.commercial.franchise_version'), 'blocked' => (bool) $h->sales_blocked]),
            'catalogue' => Product::query()->orderBy('stream')->orderBy('name')->get()->map(fn (Product $p): array => ['id' => $p->id, 'key' => $p->key, 'stream' => $p->stream, 'name' => $p->name,
                'merchant' => $p->merchant, 'metro' => $this->band($p, 'metro'), 'rural' => $this->band($p, 'rural')]),
            'rules' => collect(Product::STREAMS)->mapWithKeys(static fn (string $s): array => [$s => app(Catalogue::class)->rule($s)]),
            'recent' => Order::query()->with('product')->whereNotNull('paid_at')->latest('paid_at')->limit(30)->get()
                ->map(static fn (Order $o): array => ['id' => $o->id, 'number' => $o->number, 'product' => $o->product->name, 'total' => Money::format($o->total_cents),
                    'refundable' => $o->total_cents - $o->refunded_cents, 'status' => $o->status, 'channel' => $o->channel]),
        ]);
    }

    public function proposePrice(Request $request, Catalogue $catalogue): RedirectResponse
    {
        $user = $this->must($request, 'commercial.manage');
        $data = $request->validate(['product' => ['required', 'integer', 'exists:com_products,id'], 'tier' => ['required', Rule::in(['metro', 'rural'])], 'price' => ['required', 'integer', 'min:0'],
            'floor' => ['required', 'integer', 'min:0'], 'ceiling' => ['required', 'integer', 'min:0'], 'effective_from' => ['required', 'date', 'after_or_equal:today']]);

        return $this->attempt(fn () => $catalogue->proposePrice(Product::query()->whereKey($data['product'])->firstOrFail(), $data['tier'], (int) $data['price'], (int) $data['floor'], (int) $data['ceiling'], $data['effective_from'], $user), 'commercial.approval.requested');
    }

    public function proposeRule(Request $request, Catalogue $catalogue): RedirectResponse
    {
        $user = $this->must($request, 'commercial.manage');
        $data = $request->validate(['stream' => ['required', Rule::in(Product::STREAMS)], 'kasi' => ['required', 'numeric', 'min:0', 'max:100'], 'hub' => ['required', 'numeric', 'min:0', 'max:100'],
            'partner' => ['required', 'numeric', 'min:0', 'max:100'], 'effective_from' => ['required', 'date', 'after_or_equal:today'], 'note' => ['nullable', 'string', 'max:300']]);

        return $this->attempt(fn () => $catalogue->proposeRule($data['stream'], (int) round($data['kasi'] * 100), (int) round($data['hub'] * 100), (int) round($data['partner'] * 100),
            $data['effective_from'], $data['note'] ?? null, $user), 'commercial.approval.requested');
    }

    public function decide(Request $request, int $approval, Catalogue $catalogue, HubFinance $finance): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:300']]);
        $kind = (string) DB::table('com_approvals')->where('id', $approval)->value('kind');
        $user = $this->must($request, $kind === 'bank_account' ? 'commercial.finance' : 'commercial.manage');

        return $this->attempt(fn () => $kind === 'bank_account' ? $finance->decideBank($approval, $user, (bool) $data['approve'], $data['reason'] ?? null)
            : $catalogue->decide($approval, $user, (bool) $data['approve'], $data['reason'] ?? null), 'commercial.approval.decided');
    }

    public function invoicePaid(Request $request, string $invoice, Invoicing $invoicing): RedirectResponse
    {
        $user = $this->must($request, 'commercial.finance');
        $invoicing->markPaid($invoice, $user);

        return back()->with('status', __('commercial.invoice.paid'));
    }

    public function refund(Request $request, Order $order, Orders $orders): RedirectResponse
    {
        $user = $this->must($request, 'commercial.finance');
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:300']]);

        return $this->attempt(fn () => $orders->refund($order->load('product'), (int) $data['amount'], $user, $data['reason']), 'commercial.refund.done');
    }

    public function resolveIssue(Request $request, int $issue): RedirectResponse
    {
        $this->must($request, 'commercial.finance');
        $data = $request->validate(['note' => ['required', 'string', 'max:300']]);
        DB::table('com_reconciliation_issues')->where('id', $issue)->update(['resolved_at' => now(), 'note' => $data['note']]);

        return back();
    }

    /** Ledger CSV for the accountants (a month). */
    public function ledger(Request $request): StreamedResponse
    {
        $this->must($request, 'commercial.finance');
        $period = (string) $request->query('period', now()->format('Y-m'));
        $rows = DB::table('com_ledger')->leftJoin('hubs', 'hubs.id', '=', 'com_ledger.hub_id')->where('com_ledger.created_at', '>=', $period.'-01')
            ->where('com_ledger.created_at', '<=', CarbonImmutable::parse($period.'-01')->endOfMonth())->orderBy('com_ledger.id')
            ->get(['com_ledger.id', 'com_ledger.created_at', 'kind', 'account', 'amount_cents', 'memo', 'order_id', 'invoice_id', 'hubs.name']);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'date', 'hub', 'kind', 'account', 'amount_rand', 'memo', 'order', 'invoice'], escape: '\\');
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->created_at, $r->name, $r->kind, $r->account, number_format((int) $r->amount_cents / 100, 2, '.', ''), $r->memo, $r->order_id, $r->invoice_id], escape: '\\');
            }
            fclose($out);
        }, "ledger-{$period}.csv", ['Content-Type' => 'text/csv']);
    }

    /** @return array{price: string, floor: string, ceiling: string}|null */
    private function band(Product $p, string $tier): ?array
    {
        $b = app(Catalogue::class)->price($p, $tier);

        return $b === null ? null : ['price' => Money::format($b['price']), 'floor' => Money::format($b['floor']), 'ceiling' => Money::format($b['ceiling'])];
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['commercial' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function may(User $user, string $permission): bool
    {
        return app(AccessResolver::class)->hasPermission($user, $permission);
    }

    private function must(Request $request, string $permission): User
    {
        $user = $this->user($request);
        abort_unless($this->may($user, $permission), 403);

        return $user;
    }
}
