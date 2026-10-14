<?php

declare(strict_types=1);

namespace Modules\Commercial\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Commercial\Models\Order;
use Modules\Commercial\Models\Product;
use Modules\Commercial\Services\Catalogue;
use Modules\Commercial\Services\HubFinance;
use Modules\Commercial\Services\Money;
use Modules\Commercial\Services\Orders;
use Modules\Commercial\Services\Settlement;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\RoleAssignment;

/** Hub owners and managers: readiness checklist, the point of sale, statement, invoices and payouts. */
final class HubController
{
    public function dashboard(Request $request, HubFinance $finance, Settlement $settlement): Response
    {
        [, $hub, $isOwner] = $this->hub($request);
        $period = (string) $request->query('period', now()->format('Y-m'));
        $f = DB::table('com_hub_finance')->where('hub_id', $hub->id)->first();

        return Inertia::render('Commercial/Hub', [
            'hub' => ['id' => $hub->id, 'name' => $hub->name, 'package' => $hub->package, 'tier' => Catalogue::tier($hub)],
            'isOwner' => $isOwner, 'period' => $period,
            'checklist' => $finance->checklist($hub), 'canSell' => $finance->canSell($hub),
            'details' => ['company_registration' => $f->company_registration ?? '', 'vat_registered' => (bool) ($f->vat_registered ?? false), 'vat_number' => $f->vat_number ?? '',
                'bank' => $f->bank_account_label ?? null, 'franchiseVersion' => config('kasi.commercial.franchise_version'), 'pendingBank' => DB::table('com_approvals')
                    ->where('kind', 'bank_account')->where('subject_id', $hub->id)->where('status', 'pending')->exists()],
            'statement' => array_map([Money::class, 'format'], $settlement->statement($hub, $period)),
            'invoices' => DB::table('com_invoices')->where('hub_id', $hub->id)->where('kind', 'package')->orderByDesc('period')->limit(12)->get(['number', 'period', 'amount_cents', 'status', 'due_on'])
                ->map(static fn (object $i): array => ['number' => (string) $i->number, 'period' => (string) $i->period, 'amount' => Money::format((int) $i->amount_cents), 'status' => (string) $i->status, 'due' => (string) $i->due_on]),
            'payouts' => DB::table('com_payouts')->where('hub_id', $hub->id)->orderByDesc('period')->limit(12)->get(['period', 'amount_cents', 'status'])
                ->map(static fn (object $p): array => ['period' => (string) $p->period, 'amount' => Money::format((int) $p->amount_cents), 'status' => (string) $p->status]),
            'sales' => Order::query()->with('product')->where('hub_id', $hub->id)->whereNotNull('paid_at')->latest('paid_at')->limit(30)->get()
                ->map(static fn (Order $o): array => ['id' => $o->id, 'number' => $o->number, 'product' => $o->product->name, 'channel' => $o->channel, 'total' => Money::format($o->total_cents),
                    'hubShare' => Money::format($o->shares['hub']), 'status' => $o->status, 'at' => $o->paid_at?->toIso8601String()]),
            'posProducts' => $this->posProducts($hub),
        ]);
    }

    public function details(Request $request, HubFinance $finance): RedirectResponse
    {
        [$user, $hub] = $this->owner($request);
        $data = $request->validate(['company_registration' => ['required', 'string', 'max:30'], 'vat_registered' => ['boolean'], 'vat_number' => ['nullable', 'required_if:vat_registered,true', 'string', 'max:20']]);
        $finance->saveDetails($hub, ['company_registration' => $data['company_registration'], 'vat_registered' => (bool) ($data['vat_registered'] ?? false), 'vat_number' => $data['vat_number'] ?? null], $user);

        return back()->with('status', __('work.saved'));
    }

    public function franchise(Request $request, HubFinance $finance): RedirectResponse
    {
        [$user, $hub] = $this->owner($request);
        $request->validate(['accept' => ['accepted']]);
        $finance->acceptFranchise($hub, $user);

        return back()->with('status', __('commercial.hub.franchise_accepted'));
    }

    public function bank(Request $request, HubFinance $finance, DocumentVault $vault): RedirectResponse
    {
        [$user, $hub] = $this->owner($request);
        $data = $request->validate(['letter' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)], 'label' => ['required', 'string', 'max:60', 'regex:/^[^0-9]*[0-9]{0,4}$/']]);
        $letter = $vault->store($user, $request->file('letter'), 'bank_confirmation');
        $finance->requestBank($hub, $letter, $data['label'], null, $user);

        return back()->with('status', __('commercial.hub.bank_requested'));
    }

    /** Point of sale: cash / in-person sales at the hub; royalty netted against the next payout. */
    public function sell(Request $request, Orders $orders): RedirectResponse
    {
        [$user, $hub] = $this->hub($request);
        $data = $request->validate(['product' => ['required', 'string', Rule::in(array_column($this->posProducts($hub), 'key'))], 'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'price' => ['nullable', 'integer', 'min:0'], 'buyer' => ['nullable', 'string', 'max:160']]);
        $product = Product::query()->where('key', $data['product'])->firstOrFail();

        try {
            $order = $orders->create($product, (int) $data['quantity'], $hub, 'hub', 'pos', null, null, [], $user, isset($data['price']) ? (int) $data['price'] : null, $data['buyer'] ?? null);
            $orders->markPaid($order->load('product'));
        } catch (DomainException $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        return back()->with('status', __('commercial.pos.sold', ['number' => $order->number, 'total' => Money::format($order->total_cents)]));
    }

    /** @return list<array{key: string, name: string, unit: string, price: int, floor: int, ceiling: int}> */
    private function posProducts(Hub $hub): array
    {
        $tier = Catalogue::tier($hub);

        return array_values(Product::query()->whereIn('stream', ['onsite', 'start'])->where('active', true)->orderBy('name')->get()
            ->map(static fn (Product $p): ?array => ($band = app(Catalogue::class)->price($p, $tier)) === null ? null
                : ['key' => $p->key, 'name' => $p->name, 'unit' => $p->unit, 'price' => $band['price'], 'floor' => $band['floor'], 'ceiling' => $band['ceiling']])
            ->filter()->values()->all());
    }

    /** @return array{0: User, 1: Hub, 2: bool} */
    private function hub(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $roles = RoleAssignment::query()->where('user_id', $user->id)->where('scope_type', 'hub')->whereIn('role', ['hub_owner', 'hub_manager'])->get();
        $chosen = $roles->firstWhere('scope_id', $request->input('hub', $request->query('hub'))) ?? $roles->first();
        abort_if($chosen === null, 403);

        return [$user, Hub::query()->findOrFail($chosen->scope_id), $roles->where('scope_id', $chosen->scope_id)->contains('role', 'hub_owner')];
    }

    /** @return array{0: User, 1: Hub} */
    private function owner(Request $request): array
    {
        [$user, $hub, $isOwner] = $this->hub($request);
        abort_unless($isOwner, 403);

        return [$user, $hub];
    }
}
