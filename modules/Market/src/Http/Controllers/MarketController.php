<?php

declare(strict_types=1);

namespace Modules\Market\Http\Controllers;

use App\Support\Seo\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Models\User;
use Modules\Market\Events\ProductReported;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Shops;

/** Public KasiMarket (S24): home, search, shop and product pages, reporting. Nothing personal is shown. */
final class MarketController
{
    public function __construct(private readonly Shops $shops) {}

    public function home(Request $request): Response
    {
        return Inertia::render('Market/Home', [
            'categories' => DB::table('market_categories')->orderBy('position')->get(['id', 'slug']),
            'newest' => $this->cards(Product::query()->listed()->latest('published_at')->limit(12)),
            'shops' => Shop::query()->where('status', 'open')->when($request->query('hub'), fn ($q, $hub) => $q->where('hub_id', $hub))->latest()->limit(8)->get()
                ->map(fn (Shop $s): array => $this->shopCard($s)),
            'seo' => Seo::page(__('market.title'), __('market.seo'), '/market'),
        ]);
    }

    public function search(Request $request): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'category' => ['nullable', 'integer'], 'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'], 'hub' => ['nullable', 'string', 'max:26'], 'in_stock' => ['nullable', 'boolean']]);
        $query = Product::query()->listed()
            ->when($data['q'] ?? null, fn (Builder $q, string $text) => $q->where(fn (Builder $w) => $w->where('title', 'like', '%'.addcslashes($text, '%_').'%')
                ->orWhere('description', 'like', '%'.addcslashes($text, '%_').'%')))
            ->when($data['category'] ?? null, fn (Builder $q, int $c) => $q->where('category_id', $c))
            ->when(isset($data['min']), fn (Builder $q) => $q->where('price_cents', '>=', (int) $data['min'] * 100))
            ->when(isset($data['max']), fn (Builder $q) => $q->where('price_cents', '<=', (int) $data['max'] * 100))
            ->when($data['hub'] ?? null, fn (Builder $q, string $hub) => $q->whereHas('shop', fn (Builder $s) => $s->where('hub_id', $hub)))
            ->when((bool) ($data['in_stock'] ?? false), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('stock_type', '!=', 'quantity')->orWhere('stock', '>', 0)))
            ->latest('published_at');
        $page = $query->with(['shop', 'photos'])->paginate(24)->withQueryString();

        return Inertia::render('Market/Search', [
            'results' => $page->through(fn (Product $p): array => $this->card($p)),
            'filters' => array_filter($data, static fn ($v) => $v !== null),
            'categories' => DB::table('market_categories')->orderBy('position')->get(['id', 'slug']),
        ]);
    }

    public function shop(string $slug): Response
    {
        $shop = Shop::query()->where('slug', $slug)->where('status', 'open')->firstOrFail();

        return Inertia::render('Market/Shop', [
            'shop' => [...$this->shopCard($shop), 'description' => $shop->description, 'area' => $shop->area, 'hours' => $shop->hours ?? [], 'away' => $shop->away,
                'whatsapp' => $shop->whatsapp_public ? $shop->whatsapp : null, 'badges' => $this->shops->badges($shop)],
            'products' => $this->cards(Product::query()->listed()->where('shop_id', $shop->id)->latest('published_at')->limit(60)),
            'seo' => Seo::page($shop->name, (string) ($shop->description ?? __('market.seo')), '/market/shops/'.$shop->slug),
        ]);
    }

    public function product(Product $product): Response
    {
        abort_unless(Product::query()->listed()->whereKey($product->id)->exists(), 404);
        Product::query()->whereKey($product->id)->increment('views');
        $product->load(['shop', 'photos', 'options']);
        $url = url('/market/products/'.$product->id);

        return Inertia::render('Market/Product', [
            'product' => [...$this->card($product), 'description' => $product->description, 'unit' => $product->unit, 'stock_type' => $product->stock_type, 'stock' => $product->stock,
                'options' => $product->options->map(fn ($o): array => ['id' => $o->id, 'label' => $o->label, 'price_cents' => $o->price_cents ?? $product->price_cents,
                    'soldOut' => $product->stock_type === 'quantity' && $o->stock !== null && $o->stock <= 0])->values(),
                'photos' => $product->photos->map(fn ($ph): array => ['small' => asset('storage/'.$ph->small_path), 'medium' => asset('storage/'.$ph->medium_path), 'alt' => $ph->alt])->values()],
            'shop' => [...$this->shopCard($product->shop), 'whatsapp' => $product->shop->whatsapp_public ? $product->shop->whatsapp : null, 'away' => $product->shop->away],
            'shareUrl' => $url,
            'seo' => Seo::page($product->title, mb_substr((string) ($product->description ?? $product->title), 0, 150), '/market/products/'.$product->id, [[
                '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->title, 'description' => (string) $product->description,
                'image' => $product->photos->map(fn ($ph) => asset('storage/'.$ph->medium_path))->values()->all(),
                'offers' => ['@type' => 'Offer', 'priceCurrency' => 'ZAR', 'price' => number_format($product->price_cents / 100, 2, '.', ''), 'url' => $url,
                    'availability' => $product->soldOut() ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock'],
            ]]),
        ]);
    }

    public function report(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate(['reason' => ['required', Rule::in(['prohibited', 'scam', 'wrong_info', 'offensive', 'other'])], 'note' => ['nullable', 'string', 'max:500']]);
        DB::table('market_reports')->insert(['product_id' => $product->id, 'reported_by' => $user->id, 'reason' => $data['reason'], 'note' => $data['note'] ?? null, 'created_at' => now()]);
        // Into the review inbox through the moderation queue.
        app(ModerationService::class)->check($product->title.' '.$product->description, 'market_product', $product->id, null, useAi: false,
            extraReasons: [__('market.report.reason.'.$data['reason']).($data['note'] ?? '' ? ': '.$data['note'] : '')]);
        event(new ProductReported($user, null, ['product' => $product->id, 'reason' => $data['reason']]));

        return back()->with('status', __('market.report.thanks'));
    }

    /**
     * @param  Builder<Product>  $query
     * @return list<array<string, mixed>>
     */
    private function cards(Builder $query): array
    {
        return array_values($query->with(['shop', 'photos'])->get()->map(fn (Product $p): array => $this->card($p))->all());
    }

    /** @return array<string, mixed> */
    private function card(Product $p): array
    {
        $photo = $p->photos->first();

        return ['id' => $p->id, 'title' => $p->title, 'price_cents' => $p->price_cents, 'soldOut' => $p->soldOut(), 'shop' => $p->shop->name, 'shopSlug' => $p->shop->slug,
            'photo' => $photo !== null ? asset('storage/'.$photo->small_path) : null, 'alt' => $photo?->alt];
    }

    /** @return array<string, mixed> */
    private function shopCard(Shop $s): array
    {
        return ['name' => $s->name, 'slug' => $s->slug, 'logo' => $s->logo_path !== null ? asset('storage/'.$s->logo_path) : null,
            'hub' => $s->hub_id !== null ? DB::table('hubs')->where('id', $s->hub_id)->value('name') : null];
    }
}
