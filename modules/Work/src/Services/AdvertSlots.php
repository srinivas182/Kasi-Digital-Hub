<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Commerce\Fulfiller;
use Modules\Core\Structure\Models\Organisation;

/** KasiWork extra advert slots, delivered when the commercial console confirms payment (S19). */
final class AdvertSlots implements Fulfiller
{
    public const PRODUCT = 'work_extra_advert';

    public const DAYS = 30;

    public function products(): array
    {
        return [self::PRODUCT];
    }

    public function fulfil(string $productKey, int $quantity, array $meta): void
    {
        DB::table('work_advert_slots')->insert(['organisation_id' => (string) $meta['organisation'], 'slots' => $quantity, 'expires_at' => now()->addDays(self::DAYS),
            'order_number' => $meta['order_number'] ?? null, 'created_at' => now()]);
    }

    public function revoke(string $productKey, int $quantity, array $meta): void
    {
        DB::table('work_advert_slots')->where('organisation_id', (string) $meta['organisation'])->where('slots', $quantity)->where('expires_at', '>', now())
            ->orderByDesc('id')->limit(1)->delete();
    }

    /** Live adverts allowed now: the free limit plus paid slots that haven't expired. */
    public static function limit(Organisation $employer): int
    {
        $base = (int) config($employer->community ? 'kasi.work.community_listing_limit' : 'kasi.work.free_listing_limit');

        return $base + (int) DB::table('work_advert_slots')->where('organisation_id', $employer->id)->where('expires_at', '>', now())->sum('slots');
    }
}
