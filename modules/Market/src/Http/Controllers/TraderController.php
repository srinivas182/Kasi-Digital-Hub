<?php

declare(strict_types=1);

namespace Modules\Market\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Market\Models\Order;
use Modules\Market\Models\Product;
use Modules\Market\Models\ProductPhoto;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Products;
use Modules\Market\Services\Shops;

/** The trader's side: open and run a shop, add and publish products (S24). */
final class TraderController
{
    public function __construct(private readonly Shops $shops, private readonly Products $products, private readonly BusinessFacts $businesses) {}

    public function dashboard(Request $request): Response
    {
        $user = $this->user($request);
        $businesses = $this->businesses->businessesOf($user);
        $shop = Shop::query()->whereIn('business_id', array_column($businesses, 'id'))->first();
        if (! $shop instanceof Shop) {
            return Inertia::render('Market/Trader/Open', [
                'businesses' => array_map(fn (array $b): array => [...$b, 'blocker' => $this->shops->blocker($user, $b['id'])], $businesses),
            ]);
        }

        return Inertia::render('Market/Trader/Dashboard', [
            'shop' => $this->shopProps($shop),
            'badges' => $this->shops->badges($shop),
            'products' => $shop->products()->with('photos')->latest()->get()->map(fn (Product $p): array => $this->productRow($p)),
            'reviewed' => ['done' => min($shop->approved_products, 3), 'of' => 3],
            'newOrders' => Order::query()->where('shop_id', $shop->id)->where('status', 'placed')->count(),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['business_id' => ['required', 'string'], 'name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:600'],
            'area' => ['nullable', 'string', 'max:120'], 'whatsapp' => ['nullable', 'string', 'max:20'], 'whatsapp_public' => ['boolean']]);
        try {
            $this->shops->open($user, $data['business_id'], $data);
        } catch (DomainException $e) {
            return back()->withErrors(['shop' => $e->getMessage()]);
        }

        return to_route('market.shop')->with('status', __('market.shop.opened'));
    }

    public function update(Request $request): RedirectResponse
    {
        [$user, $shop] = $this->mine($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:600'], 'area' => ['nullable', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:20'], 'whatsapp_public' => ['boolean'], 'away' => ['boolean'], 'hours' => ['nullable', 'array'], 'hours.*' => ['nullable', 'string', 'max:20'],
            'offers_collection' => ['boolean'], 'collection_note' => ['nullable', 'string', 'max:200'], 'offers_delivery' => ['boolean'], 'delivery_area' => ['nullable', 'string', 'max:120'],
            'delivery_fee_cents' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'min_order_cents' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'accepts_online' => ['boolean'], 'accepts_cash' => ['boolean'], 'offers_hub_collection' => ['boolean'], 'free_delivery_over_cents' => ['nullable', 'integer', 'min:0'],
            'delivery_zones' => ['nullable', 'array', 'max:5'], 'delivery_zones.*.name' => ['required', 'string', 'max:40'], 'delivery_zones.*.fee_cents' => ['required', 'integer', 'min:0']]);
        if (array_key_exists('delivery_zones', $data)) {
            $data['delivery_zones'] = $data['delivery_zones'] === null || $data['delivery_zones'] === [] ? null : json_encode(array_values($data['delivery_zones']));
        }
        $this->shops->update($shop, $user, $data);

        return back()->with('status', __('work.saved'));
    }

    /** Upload a bank confirmation letter; it goes to the document queue, and once verified the shop can take online payment. */
    public function bank(Request $request, DocumentVault $vault): RedirectResponse
    {
        [$user, $shop] = $this->mine($request);
        $request->validate(['letter' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)]]);
        $letter = $vault->store($user, $request->file('letter'), 'bank_confirmation');
        $shop->forceFill(['bank_document_id' => $letter->id, 'bank_verified' => false])->save();

        return back()->with('status', __('market.pay.letter_sent'));
    }

    public function picture(Request $request): RedirectResponse
    {
        [, $shop] = $this->mine($request);
        $data = $request->validate(['kind' => ['required', Rule::in(['logo', 'cover'])], 'picture' => ['required', File::image()->max(8 * 1024)]]);
        $this->shops->picture($shop, $request->file('picture'), $data['kind']);

        return back()->with('status', __('work.saved'));
    }

    public function form(Request $request, ?Product $product = null): Response
    {
        [, $shop] = $this->mine($request);
        abort_if($product !== null && $product->shop_id !== $shop->id, 404);

        return Inertia::render('Market/Trader/Product', [
            'product' => $product !== null ? [...$this->productRow($product->load('photos', 'options')), 'description' => $product->description, 'category_id' => $product->category_id,
                'unit' => $product->unit, 'stock_type' => $product->stock_type, 'stock' => $product->stock,
                'options' => $product->options->map(fn ($o): array => ['label' => $o->label, 'price_cents' => $o->price_cents, 'stock' => $o->stock])->values(),
                'photoList' => $product->photos->map(fn (ProductPhoto $ph): array => ['id' => $ph->id, 'url' => asset('storage/'.$ph->small_path), 'alt' => $ph->alt])->values()] : null,
            'categories' => DB::table('market_categories')->orderBy('position')->get(['id', 'slug']),
            'units' => Product::UNITS, 'stockTypes' => Product::STOCK_TYPES,
        ]);
    }

    public function save(Request $request, ?Product $product = null): RedirectResponse
    {
        [$user, $shop] = $this->mine($request);
        abort_if($product !== null && $product->shop_id !== $shop->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'], 'category_id' => ['required', 'integer', 'exists:market_categories,id'], 'price_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'description' => ['nullable', 'string', 'max:3000'], 'unit' => ['required', Rule::in(Product::UNITS)], 'stock_type' => ['required', Rule::in(Product::STOCK_TYPES)],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'], 'options' => ['array', 'max:20'], 'options.*.label' => ['required', 'string', 'max:40'],
            'options.*.price_cents' => ['nullable', 'integer', 'min:1'], 'options.*.stock' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', File::image()->max(8 * 1024)], 'photo_alt' => ['nullable', 'required_with:photo', 'string', 'max:150'], 'publish' => ['boolean'],
        ]);
        $saved = $this->products->save($shop, $user, $data, $data['options'] ?? [], $product);
        if ($request->hasFile('photo')) {
            try {
                $this->products->photo($saved, $request->file('photo'), (string) $data['photo_alt']);
            } catch (DomainException $e) {
                return back()->withErrors(['photo' => $e->getMessage()]);
            }
        }
        if ((bool) ($data['publish'] ?? false)) {
            return $this->publishAndReport($saved, $user);
        }

        return to_route('market.products.edit', $saved)->with('status', __('work.saved'));
    }

    public function publish(Request $request, Product $product): RedirectResponse
    {
        [$user, $shop] = $this->mine($request);
        abort_if($product->shop_id !== $shop->id, 404);

        return $this->publishAndReport($product, $user);
    }

    public function hide(Request $request, Product $product): RedirectResponse
    {
        [$user, $shop] = $this->mine($request);
        abort_if($product->shop_id !== $shop->id, 404);
        $this->products->hide($product, $user, (bool) $request->boolean('hidden'));

        return back();
    }

    public function removePhoto(Request $request, Product $product, ProductPhoto $photo): RedirectResponse
    {
        [, $shop] = $this->mine($request);
        abort_if($product->shop_id !== $shop->id || $photo->product_id !== $product->id, 404);
        $this->products->removePhoto($photo);

        return back();
    }

    private function publishAndReport(Product $product, User $user): RedirectResponse
    {
        try {
            $decision = $this->products->publish($product, $user);
        } catch (DomainException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }
        $message = match ($decision['status']) {
            'published' => __('market.product.published'),
            'review' => __('market.product.in_review'),
            default => __('market.product.refused', ['reason' => __((string) $decision['reason'])]),
        };

        return to_route('market.shop')->with('status', $message);
    }

    /** @return array<string, mixed> */
    private function shopProps(Shop $shop): array
    {
        return ['id' => $shop->id, 'slug' => $shop->slug, 'name' => $shop->name, 'description' => $shop->description, 'area' => $shop->area, 'whatsapp' => $shop->whatsapp,
            'whatsapp_public' => $shop->whatsapp_public, 'hours' => $shop->hours ?? [], 'away' => $shop->away, 'status' => $shop->status,
            'logo' => $shop->logo_path !== null ? asset('storage/'.$shop->logo_path) : null,
            'offers_collection' => (bool) $shop->getAttribute('offers_collection'), 'collection_note' => $shop->getAttribute('collection_note'),
            'offers_delivery' => (bool) $shop->getAttribute('offers_delivery'), 'delivery_area' => $shop->getAttribute('delivery_area'),
            'delivery_fee_cents' => (int) $shop->getAttribute('delivery_fee_cents'), 'min_order_cents' => (int) $shop->getAttribute('min_order_cents'),
            'accepts_online' => (bool) $shop->getAttribute('accepts_online'), 'accepts_cash' => (bool) ($shop->getAttribute('accepts_cash') ?? true),
            'bank' => $shop->getAttribute('bank_verified') ? 'verified' : ($shop->getAttribute('bank_document_id') !== null ? 'pending' : 'none'),
            'offers_hub_collection' => (bool) $shop->getAttribute('offers_hub_collection'),
            'hubPoint' => $shop->hub_id !== null && DB::table('market_collection_points')->where('hub_id', $shop->hub_id)->where('enabled', true)->exists(),
            'delivery_zones' => (array) json_decode((string) ($shop->getAttributes()['delivery_zones'] ?? '[]'), true),
            'free_delivery_over_cents' => $shop->getAttribute('free_delivery_over_cents') !== null ? (int) $shop->getAttribute('free_delivery_over_cents') : null];
    }

    /** @return array<string, mixed> */
    private function productRow(Product $p): array
    {
        return ['id' => $p->id, 'title' => $p->title, 'price_cents' => $p->price_cents, 'status' => $p->status, 'reason' => $p->review_reason !== null ? __($p->review_reason) : null,
            'stock_type' => $p->stock_type, 'stock' => $p->stock, 'soldOut' => $p->soldOut(), 'views' => $p->views,
            'photo' => $p->photos->first() !== null ? asset('storage/'.$p->photos->first()->small_path) : null];
    }

    /** @return array{0: User, 1: Shop} */
    private function mine(Request $request): array
    {
        $user = $this->user($request);
        $shop = Shop::query()->whereIn('business_id', array_column($this->businesses->businessesOf($user), 'id'))->first();
        abort_unless($shop instanceof Shop, 404);

        return [$user, $shop];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
