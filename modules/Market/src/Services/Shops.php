<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\PhoneNumbers;
use Modules\Core\Media\WebpImage;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Market\Events\ShopOpened;
use Modules\Market\Models\Shop;

/**
 * Shops (ADR-032): one per KasiStart business (informal ones too), opened by an owner or co-owner aged 18+ whose
 * hub has KasiMarket. The business WhatsApp number is shown only with the trader's consent.
 */
final readonly class Shops
{
    public const MIN_AGE = 18;

    public function __construct(private BusinessFacts $businesses, private AuditLogger $audit) {}

    /** Why this person can't open a shop for this business, or null when they can. */
    public function blocker(User $user, string $businessId): ?string
    {
        if (! $this->businesses->isMember($businessId, $user)) {
            return 'market.shop.not_member';
        }
        if ($user->date_of_birth->age < self::MIN_AGE) {
            return 'market.shop.age';
        }
        $hubId = $this->businesses->facts($businessId)['hub_id'] ?? $user->home_hub_id;
        if ($hubId === null || ! HubModule::query()->where('hub_id', $hubId)->where('module', 'Market')->where('enabled', true)->exists()) {
            return 'market.shop.hub';
        }
        if (Shop::query()->where('business_id', $businessId)->exists()) {
            return 'market.shop.exists';
        }

        return null;
    }

    /** @param array{name: string, description?: string|null, area?: string|null, whatsapp?: string|null, whatsapp_public?: bool} $data */
    public function open(User $user, string $businessId, array $data): Shop
    {
        if (($blocker = $this->blocker($user, $businessId)) !== null) {
            throw new DomainException(__($blocker));
        }
        $facts = (array) $this->businesses->facts($businessId);
        $shop = Shop::query()->create([
            'business_id' => $businessId, 'slug' => $this->slug($data['name']), 'name' => $data['name'], 'description' => $data['description'] ?? null,
            'hub_id' => $facts['hub_id'] ?? $user->home_hub_id, 'area' => $data['area'] ?? null, 'status' => 'open', 'away' => false, 'approved_products' => 0,
            'whatsapp' => isset($data['whatsapp']) && $data['whatsapp'] !== '' ? PhoneNumbers::normalise((string) $data['whatsapp']) : null,
            'whatsapp_public' => (bool) ($data['whatsapp_public'] ?? false), 'opened_by' => $user->id,
        ]);
        if (! RoleAssignment::query()->where('user_id', $user->id)->where('role', 'trader')->exists()) {
            app(RoleAssignments::class)->assign($user, 'trader', Scope::self(), $user); // as KasiStart does for entrepreneurs
        }
        $this->audit->record('market.shop_opened', meta: ['shop' => $shop->id], actor: $user);
        event(new ShopOpened($user, null, ['shop' => $shop->id]));

        return $shop;
    }

    /** @param array<string, mixed> $data */
    public function update(Shop $shop, User $by, array $data): void
    {
        if (array_key_exists('whatsapp', $data)) {
            $data['whatsapp'] = $data['whatsapp'] !== null && $data['whatsapp'] !== '' ? PhoneNumbers::normalise((string) $data['whatsapp']) : null;
        }
        $shop->fill(array_intersect_key($data, array_flip(['name', 'description', 'area', 'whatsapp', 'whatsapp_public', 'hours', 'away', 'offers_collection', 'collection_note', 'offers_delivery', 'delivery_area', 'delivery_fee_cents', 'min_order_cents'])))->save();
        $this->audit->record('market.shop_updated', meta: ['shop' => $shop->id], actor: $by);
    }

    public function picture(Shop $shop, UploadedFile $file, string $kind): void
    {
        $webp = WebpImage::make((string) file_get_contents((string) $file->getRealPath()), $kind === 'logo' ? 256 : 1200, 70);
        $path = "shops/{$shop->id}/{$kind}-".Str::random(8).'.webp';
        Storage::disk('public')->put($path, $webp);
        $shop->forceFill([$kind.'_path' => $path])->save();
    }

    /** Is this person allowed to run this shop (an owner or co-owner of its business)? */
    public function canManage(Shop $shop, User $user): bool
    {
        return $this->businesses->isMember($shop->business_id, $user);
    }

    /**
     * Badges from facts the platform already holds.
     *
     * @return list<string>
     */
    public function badges(Shop $shop): array
    {
        $facts = $this->businesses->facts($shop->business_id);
        $badges = [];
        if ($facts !== null && $facts['legal_form'] !== null && in_array('cipc_register', $facts['steps_done'], true)) {
            $badges[] = 'registered';
        }
        if ($shop->opened_by !== null && DB::table('documents')->where('user_id', $shop->opened_by)->where('type', 'id_document')->where('status', 'verified')->exists()) {
            $badges[] = 'id_verified';
        }
        if ($shop->hub_id !== null) {
            $badges[] = 'hub';
        }

        return $badges;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $n = 2;
        while (Shop::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
