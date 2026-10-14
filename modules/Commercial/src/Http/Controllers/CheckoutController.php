<?php

declare(strict_types=1);

namespace Modules\Commercial\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Commercial\Models\Order;
use Modules\Commercial\Models\Product;
use Modules\Commercial\Payments\FakeGateway;
use Modules\Commercial\Services\Catalogue;
use Modules\Commercial\Services\Money;
use Modules\Commercial\Services\Orders;
use Modules\Commercial\Services\PaymentEvents;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Online checkout (employers buying advert slots, KasiStart help), the provider's notifications, the
 * fake payment page (CI/demo), order status and tax invoices / receipts.
 */
final class CheckoutController
{
    /** Products sold online in Release 1, and who may buy them. */
    private const ONLINE = ['work_extra_advert' => 'organisation', 'start_assisted' => 'person'];

    public function show(Request $request, string $key, Orders $orders, Catalogue $catalogue): Response
    {
        [$user, $product, $organisation] = $this->buyable($request, $key);
        [$hub, $attribution] = $orders->attribute($user, $request->query('hub') !== null ? (string) $request->query('hub') : null, $this->assistingHub($request));
        $band = $catalogue->price($product, Catalogue::tier($hub));
        abort_if($band === null, 404);

        return Inertia::render('Commercial/Checkout', [
            'product' => ['key' => $product->key, 'name' => $product->name, 'unit' => $product->unit],
            'price' => Money::format($band['price']), 'priceCents' => $band['price'],
            'organisation' => $organisation?->displayName(), 'organisationId' => $organisation?->id,
            'hub' => $hub?->name, 'attribution' => $attribution, 'refHub' => $request->query('hub'),
        ]);
    }

    public function start(Request $request, string $key, Orders $orders): \Symfony\Component\HttpFoundation\Response
    {
        [$user, $product, $organisation] = $this->buyable($request, $key);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:20'], 'hub' => ['nullable', 'string', 'max:24']]);
        [$hub, $attribution] = $orders->attribute($user, $data['hub'] ?? null, $this->assistingHub($request));

        try {
            $order = $orders->create($product, (int) $data['quantity'], $hub, $attribution, 'online', $user, $organisation?->id,
                array_filter(['organisation' => $organisation?->id, 'person' => $user->id]));
            $order->forceFill(['meta' => [...(array) $order->meta, 'order_number' => $order->number]])->save();
            $start = $orders->checkout($order->load('product'), (string) ($user->email ?? 'payments@kasidigitalhub.co.za'), url('/commercial/orders/'.$order->id));
        } catch (DomainException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        return Inertia::location($start['url']);
    }

    public function order(Request $request, Order $order): Response
    {
        $user = $this->owner($request, $order);

        return Inertia::render('Commercial/Order', ['order' => [
            'id' => $order->id, 'number' => $order->number, 'product' => $order->product->name, 'quantity' => $order->quantity, 'status' => $order->status,
            'total' => Money::format($order->total_cents), 'vat' => Money::format($order->vat_cents), 'merchant' => $order->merchant, 'paidAt' => $order->paid_at?->toIso8601String(),
            'next' => $order->product->key === 'work_extra_advert' ? '/work/employer' : null,
        ], 'viewer' => $user->first_name]);
    }

    /** Tax invoice / receipt (verifiable PDF). */
    public function receipt(Request $request, Order $order, DocumentIssuer $issuer): StreamedResponse
    {
        $user = $this->owner($request, $order);
        abort_unless(in_array($order->status, ['paid', 'part_refunded', 'refunded'], true), 404);
        $hub = $order->hub_id !== null ? Hub::query()->find($order->hub_id) : null;
        $finance = $hub !== null ? DB::table('com_hub_finance')->where('hub_id', $hub->id)->first() : null;
        $document = $issuer->issue(owner: $user, type: 'commercial_receipt', title: ($order->vat_cents > 0 ? 'Tax invoice ' : 'Receipt ').$order->number, view: 'commercial::receipt',
            data: ['order' => $order, 'product' => $order->product, 'seller' => $order->merchant === 'hub' && $hub !== null ? $hub->name.' (Kasi Digital Hub)' : 'Ku Tirhisana Consultancy (Pty) Ltd',
                'sellerVat' => $order->merchant === 'hub' ? ($finance->vat_number ?? null) : config('kasi.brand.vat_number'), 'buyer' => $order->buyer_name ?? $user->fullName()],
            templateVersion: 1, subject: ['commercial_order', $order->id]);

        return $issuer->download($document);
    }

    /** Provider notifications (no session; signature checked). */
    public function webhook(Request $request, PaymentEvents $events): HttpResponse
    {
        $headers = array_map(static fn (array $v): string => (string) ($v[0] ?? ''), $request->headers->all());

        return response('', $events->handle($headers, $request->getContent()) ? 200 : 400);
    }

    /** The fake provider's payment page (CI, demo). */
    public function fakePage(string $reference): Response
    {
        abort_unless(config('kasi.commercial.payments_driver') === 'fake', 404);
        $pending = Cache::get('fake-pay:'.$reference);
        abort_if(! is_array($pending), 404);

        return Inertia::render('Commercial/FakePay', ['reference' => $reference, 'amount' => Money::format((int) $pending['amount'])]);
    }

    public function fakePay(Request $request, string $reference, PaymentEvents $events): RedirectResponse
    {
        abort_unless(config('kasi.commercial.payments_driver') === 'fake', 404);
        $pending = Cache::get('fake-pay:'.$reference);
        abort_if(! is_array($pending), 404);
        $ok = $request->input('outcome') === 'pay';
        $body = (string) json_encode(['id' => 'fake-'.$reference.'-'.($ok ? 'ok' : 'fail'), 'event' => $ok ? 'charge.success' : 'charge.failed',
            'reference' => $reference, 'amount' => (int) $pending['amount'], 'status' => $ok ? 'success' : 'failed']);
        $events->handle(['x-fake-signature' => FakeGateway::sign($body)], $body);

        return redirect()->to((string) $pending['return']);
    }

    /** @return array{0: User, 1: Product, 2: Organisation|null} */
    private function buyable(Request $request, string $key): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(isset(self::ONLINE[$key]), 404);
        $product = Product::query()->where('key', $key)->where('active', true)->firstOrFail();
        $organisation = null;
        if (self::ONLINE[$key] === 'organisation') {
            $organisation = Organisation::query()->find($request->input('organisation', $request->query('organisation')));
            abort_unless($organisation instanceof Organisation && RoleAssignment::query()->where('user_id', $user->id)->where('scope_type', 'organisation')
                ->where('scope_id', $organisation->id)->whereIn('role', ['employer_admin'])->exists(), 403);
        }

        return [$user, $product, $organisation];
    }

    private function assistingHub(Request $request): ?string
    {
        $user = $request->user();
        if (! $user instanceof User || app(AssistedSession::class)->person($user) === null) {
            return null;
        }

        return RoleAssignment::query()->where('user_id', $user->id)->where('scope_type', 'hub')->value('scope_id');
    }

    private function owner(Request $request, Order $order): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && ($order->buyer_user_id === $user->id || $order->getAttribute('created_by') === $user->id), 404);
        $order->loadMissing('product');

        return $user;
    }
}
