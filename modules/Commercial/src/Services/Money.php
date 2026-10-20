<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use InvalidArgumentException;

/**
 * Money arithmetic (ADR-027). All amounts are integer cents (ZAR). Prices are VAT-inclusive.
 * - VAT (15%) is extracted from the inclusive total only when the merchant of record is VAT-registered,
 *   and stays with that merchant.
 * - Shares are calculated on the amount excluding VAT. Hub and partner shares are rounded down; the
 *   remaining cent(s) go to Kasi, so the shares always add up exactly.
 */
final class Money
{
    public const VAT_BP = 1500; // 15.00%

    /** @return array{net: int, vat: int} */
    public static function vat(int $totalCents, bool $vatRegistered): array
    {
        if ($totalCents < 0) {
            throw new InvalidArgumentException('Negative total');
        }
        $vat = $vatRegistered ? (int) round($totalCents * self::VAT_BP / (10000 + self::VAT_BP)) : 0;

        return ['net' => $totalCents - $vat, 'vat' => $vat];
    }

    /** @return array{kasi: int, hub: int, partner: int} */
    public static function split(int $netCents, int $kasiBp, int $hubBp, int $partnerBp = 0): array
    {
        if ($kasiBp + $hubBp + $partnerBp !== 10000) {
            throw new InvalidArgumentException('Split must add up to 100%');
        }
        $hub = intdiv($netCents * $hubBp, 10000);
        $partner = intdiv($netCents * $partnerBp, 10000);

        return ['kasi' => $netCents - $hub - $partner, 'hub' => $hub, 'partner' => $partner];
    }

    /**
     * Reverse a share proportionally for a (partial) refund, using the same rounding rule.
     *
     * @param  array{kasi: int, hub: int, partner: int}  $shares
     * @return array{kasi: int, hub: int, partner: int}
     */
    public static function proportion(array $shares, int $part, int $whole): array
    {
        if ($whole <= 0 || $part < 0 || $part > $whole) {
            throw new InvalidArgumentException('Bad proportion');
        }
        $hub = intdiv($shares['hub'] * $part, $whole);
        $partner = intdiv($shares['partner'] * $part, $whole);
        $total = intdiv(($shares['kasi'] + $shares['hub'] + $shares['partner']) * $part, $whole);

        return ['kasi' => $total - $hub - $partner, 'hub' => $hub, 'partner' => $partner];
    }

    /**
     * Reverse any set of shares proportionally (S26: also with a seller), with the same rounding rule: every share
     * except Kasi's rounds down, Kasi takes the remaining cent(s), so the parts always add up.
     *
     * @param  array<string, int>  $shares
     * @return array<string, int>
     */
    public static function proportionAll(array $shares, int $part, int $whole): array
    {
        if ($whole <= 0 || $part < 0 || $part > $whole) {
            throw new InvalidArgumentException('Bad proportion');
        }
        $total = intdiv(array_sum($shares) * $part, $whole);
        $out = [];
        foreach ($shares as $key => $value) {
            if ($key !== 'kasi') {
                $out[$key] = intdiv($value * $part, $whole);
            }
        }

        return ['kasi' => $total - array_sum($out)] + $out;
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '').'R'.number_format(abs($cents) / 100, 2, '.', ' ');
    }
}
