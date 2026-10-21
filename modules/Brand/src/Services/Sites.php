<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Brand\Events\SitePublished;
use Modules\Brand\Notifications\BrandNotification;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Enterprise\BusinessProducts;
use Modules\Core\Enterprise\Signals;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Media\WebpImage;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Safety\ProhibitedItems;

/**
 * One-page business websites (ADR-039): an address (name.<domain> and /b/name), content from the brand kit and
 * KasiMarket, publishing only for owners with a verified ID, moderation and the prohibited-items list, take-down by
 * moderators, and private counts (no cookies, no personal data).
 */
final readonly class Sites
{
    public const RESERVED = ['www', 'api', 'mail', 'email', 'smtp', 'ftp', 'cdn', 'static', 'assets', 'media', 'files', 'img', 'staging', 'test', 'demo', 'dev',
        'help', 'support', 'status', 'docs', 'blog', 'news', 'login', 'signin', 'register', 'account', 'b', 'shop', 'kasi', 'kasihub', 'kasidigitalhub', 'official', 'gov', 'sars', 'cipc'];

    /** A short placeholder list of offensive words; to be extended with Ku Tirhisana. */
    public const OFFENSIVE = ['fuck', 'shit', 'kaffir', 'kaffer', 'naai', 'poes', 'sex', 'porn', 'nude'];

    public const MAX_PHOTOS = 6;

    public const MAX_SERVICES = 12;

    public function __construct(private AuditLogger $audit, private ModerationService $moderation, private BusinessFacts $businesses) {}

    /** Why this address can't be used for this business, or null if it can. */
    public function nameProblem(string $name, string $businessId): ?string
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $name) !== 1 || str_contains($name, '--')) {
            return 'format';
        }
        $portals = array_keys((array) config('kasi.subdomains.portals', []));
        if (in_array($name, [...self::RESERVED, ...$portals], true)) {
            return 'reserved';
        }
        foreach (self::OFFENSIVE as $word) {
            if (str_contains($name, $word)) {
                return 'offensive';
            }
        }
        if (DB::table('brand_sites')->where('name', $name)->where('business_id', '!=', $businessId)->exists()) {
            return 'taken';
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function site(string $businessId): ?array
    {
        $s = DB::table('brand_sites')->where('business_id', $businessId)->first();

        return $s === null ? null : ['name' => $s->name, 'about' => $s->about, 'services' => json_decode((string) ($s->services ?? '[]'), true) ?: [],
            'use_market' => (bool) $s->use_market, 'status' => (string) $s->status, 'held_reason' => $s->held_reason, 'published_at' => $s->published_at,
            'can_rename' => $s->name_changed_at === null || now()->subMonth()->gt($s->name_changed_at)];
    }

    /** @param array{name?: string|null, about?: string|null, services?: list<array<string, mixed>>, use_market?: bool} $data */
    public function save(string $businessId, User $by, array $data): void
    {
        $current = DB::table('brand_sites')->where('business_id', $businessId)->first();
        $values = ['about' => isset($data['about']) ? mb_substr(trim((string) $data['about']), 0, 600) : null, 'use_market' => (bool) ($data['use_market'] ?? true),
            'services' => json_encode(array_slice(array_values(array_filter(array_map(static fn (array $s): array => ['name' => mb_substr(trim((string) ($s['name'] ?? '')), 0, 60),
                'price' => mb_substr(trim((string) ($s['price'] ?? '')), 0, 20)], $data['services'] ?? []), static fn (array $s): bool => $s['name'] !== '')), 0, self::MAX_SERVICES)),
            'updated_at' => now()];
        $name = isset($data['name']) ? strtolower(trim((string) $data['name'])) : null;
        if ($name !== null && $name !== '' && $name !== ($current->name ?? null)) {
            if ($current?->name !== null && $current->name_changed_at !== null && now()->subMonth()->lt($current->name_changed_at)) {
                throw new DomainException(__('brand.site.rename_monthly'));
            }
            $problem = $this->nameProblem($name, $businessId);
            if ($problem !== null) {
                throw new DomainException(__('brand.site.name_'.$problem));
            }
            $values['name'] = $name;
            $values['name_changed_at'] = $current?->name !== null ? now() : null;
        }
        $row = [...$values, 'created_at' => $current->created_at ?? now()];
        // Atomic insert-or-update (S31): two saves at once for the same business can't both insert.
        DB::table('brand_sites')->upsert([['business_id' => $businessId, ...$row]], ['business_id'], array_values(array_diff(array_keys($row), ['created_at'])));
        $this->forget($businessId);
        $this->audit->record('brand.site_saved', meta: ['business' => $businessId], actor: $by);
    }

    public function addPhoto(string $businessId, User $by, UploadedFile $file, string $alt): void
    {
        if (DB::table('brand_site_photos')->where('business_id', $businessId)->count() >= self::MAX_PHOTOS) {
            throw new DomainException(__('brand.site.photos_full'));
        }
        $path = "brand/{$businessId}/site-".Str::random(10).'.webp';
        Storage::disk('public')->put($path, WebpImage::make((string) file_get_contents((string) $file->getRealPath()), 1000, 75)); // metadata stripped
        DB::table('brand_site_photos')->insert(['business_id' => $businessId, 'path' => $path, 'alt' => mb_substr(trim($alt), 0, 120),
            'position' => DB::table('brand_site_photos')->where('business_id', $businessId)->count(), 'created_at' => now()]);
        $this->forget($businessId);
    }

    public function removePhoto(string $businessId, int $photoId): void
    {
        $path = DB::table('brand_site_photos')->where('id', $photoId)->where('business_id', $businessId)->value('path');
        if (is_string($path)) {
            Storage::disk('public')->delete($path);
            DB::table('brand_site_photos')->where('id', $photoId)->delete();
            $this->forget($businessId);
        }
    }

    /** Publish: a verified ID, an address, nothing prohibited; text that moderation flags is held for KasiHub review. */
    public function publish(string $businessId, User $owner, User $by): string
    {
        $site = DB::table('brand_sites')->where('business_id', $businessId)->first();
        if ($site === null || $site->name === null) {
            throw new DomainException(__('brand.site.needs_name'));
        }
        if ($site->status === 'taken_down') {
            throw new DomainException(__('brand.site.taken_down_help'));
        }
        if (! DB::table('documents')->where('user_id', $owner->id)->where('type', 'id_document')->where('status', 'verified')->exists()) {
            throw new DomainException(__('brand.site.needs_id'));
        }
        $text = implode("\n", [(string) ($this->businesses->facts($businessId)['name'] ?? ''), (string) $site->about,
            ...array_map(static fn (array $s): string => $s['name'], json_decode((string) ($site->services ?? '[]'), true) ?: [])]);
        $prohibited = ProhibitedItems::reason($text);
        if ($prohibited !== null) {
            throw new DomainException(__('brand.site.prohibited', ['what' => __('market.prohibited.'.$prohibited)]));
        }
        $flagged = $this->moderation->check($text, 'brand_site', $businessId, $by, useAi: false)['verdict'] === 'flag';
        $status = $flagged ? 'held' : 'published';
        DB::table('brand_sites')->where('business_id', $businessId)->update(['status' => $status, 'held_reason' => null, 'published_at' => $site->published_at ?? now(), 'updated_at' => now()]);
        $this->forget($businessId);
        $this->audit->record('brand.site_published', meta: ['business' => $businessId, 'status' => $status], actor: $by);
        if ($status === 'published' && $site->published_at === null) {
            event(new SitePublished($owner, $by->id !== $owner->id ? $by->id : null, ['business' => $businessId]));
        }

        return $status;
    }

    public function unpublish(string $businessId, User $by): void
    {
        DB::table('brand_sites')->where('business_id', $businessId)->where('status', '!=', 'taken_down')->update(['status' => 'draft', 'updated_at' => now()]);
        $this->forget($businessId);
        $this->audit->record('brand.site_unpublished', meta: ['business' => $businessId], actor: $by);
    }

    /** KasiHub moderators take a site down (the owner is told why) or restore it after an appeal. */
    public function takeDown(string $businessId, User $moderator, string $reason, bool $restore = false): void
    {
        DB::table('brand_sites')->where('business_id', $businessId)->update(['status' => $restore ? 'draft' : 'taken_down',
            'held_reason' => $restore ? null : mb_substr($reason, 0, 300), 'updated_at' => now()]);
        $this->forget($businessId);
        $this->audit->record($restore ? 'brand.site_restored' : 'brand.site_taken_down', meta: ['business' => $businessId, 'reason' => $reason], actor: $moderator);
        foreach ($this->businesses->facts($businessId)['owner_ids'] ?? [] as $ownerId) {
            $owner = User::query()->find($ownerId);
            if ($owner instanceof User) {
                app(Notifier::class)->send($owner, new BrandNotification($restore ? 'site_restored' : 'site_taken_down', ['reason' => $reason], '/brand/site'));
            }
        }
    }

    /**
     * Everything the public page shows (only what is public), cached until the site changes.
     *
     * @return array<string, mixed>|null
     */
    public function publicData(string $name): ?array
    {
        $businessId = DB::table('brand_sites')->where('name', $name)->where('status', 'published')->value('business_id');
        if (! is_string($businessId)) {
            return null;
        }

        return Cache::remember('brand.site.'.$businessId, now()->addMinutes(10), function () use ($businessId): array {
            $site = (array) $this->site($businessId);
            $kit = app(Kits::class)->kit($businessId);
            $shop = app(BusinessProducts::class)->shop($businessId);
            $facts = $this->businesses->facts($businessId);

            return ['business_id' => $businessId, 'name' => (string) ($facts['name'] ?? ''), 'address' => (string) $site['name'], 'about' => $site['about'], 'kit' => $kit,
                'logo_svg' => Kits::textLogoSvg($kit['logo_text'], $kit['logo_shape'], $kit['primary'], $kit['secondary']), 'on_primary' => Kits::readableOn($kit['primary']),
                'services' => $site['use_market'] ? array_slice(app(BusinessProducts::class)->products($businessId, self::MAX_SERVICES), 0, self::MAX_SERVICES) : [],
                'typed' => $site['use_market'] ? [] : $site['services'],
                'photos' => DB::table('brand_site_photos')->where('business_id', $businessId)->orderBy('position')->get(['path', 'alt'])
                    ->map(static fn (object $p): array => ['url' => asset('storage/'.$p->path), 'alt' => (string) $p->alt])->all(),
                'shop' => $shop, 'rating' => Signals::for($businessId)['market.rating'] ?? null];
        });
    }

    /** Count a view or a tap (no cookies, no personal data). */
    public static function count(string $businessId, string $what): void
    {
        $column = ['view' => 'views', 'whatsapp' => 'whatsapp_taps', 'order' => 'order_taps'][$what] ?? null;
        if ($column === null) {
            return;
        }
        DB::table('brand_site_stats')->insertOrIgnore(['business_id' => $businessId, 'day' => now()->toDateString()]);
        DB::table('brand_site_stats')->where('business_id', $businessId)->where('day', now()->toDateString())->increment($column);
    }

    /** @return list<array{week: string, views: int, whatsapp: int, orders: int}> last 8 weeks */
    public static function stats(string $businessId): array
    {
        $weeks = [];
        foreach (DB::table('brand_site_stats')->where('business_id', $businessId)->where('day', '>=', now()->subWeeks(8)->toDateString())->get() as $d) {
            $w = CarbonImmutable::parse((string) $d->day)->startOfWeek()->toDateString();
            $weeks[$w] ??= ['week' => $w, 'views' => 0, 'whatsapp' => 0, 'orders' => 0];
            $weeks[$w]['views'] += (int) $d->views;
            $weeks[$w]['whatsapp'] += (int) $d->whatsapp_taps;
            $weeks[$w]['orders'] += (int) $d->order_taps;
        }
        ksort($weeks);

        return array_values($weeks);
    }

    public static function url(string $name): string
    {
        return (bool) config('kasi.subdomains.enabled') ? 'https://'.$name.'.'.config('kasi.subdomains.domain') : url('/b/'.$name);
    }

    private function forget(string $businessId): void
    {
        Cache::forget('brand.site.'.$businessId);
    }
}
