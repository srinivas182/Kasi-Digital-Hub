<?php

declare(strict_types=1);

use Modules\Commercial\Services\Money;

it('extracts 15% VAT from VAT-inclusive totals only for VAT-registered merchants', function (): void {
    expect(Money::vat(11500, true))->toBe(['net' => 10000, 'vat' => 1500])
        ->and(Money::vat(11500, false))->toBe(['net' => 11500, 'vat' => 0])
        ->and(Money::vat(999, true))->toBe(['net' => 869, 'vat' => 130]);
});

it('splits to the exact cent, rounding hub and partner down and giving the remainder to Kasi', function (): void {
    expect(Money::split(10000, 3000, 7000))->toBe(['kasi' => 3000, 'hub' => 7000, 'partner' => 0])
        ->and(Money::split(9999, 3333, 3333, 3334))->toBe(['kasi' => 3334, 'hub' => 3332, 'partner' => 3333]);

    foreach ([1, 7, 99, 12345, 870_000] as $net) {
        $s = Money::split($net, 2500, 5500, 2000);
        expect(array_sum($s))->toBe($net);
    }
    expect(fn () => Money::split(100, 5000, 4000))->toThrow(InvalidArgumentException::class);
});

it('reverses shares proportionally for partial refunds, always adding up', function (): void {
    $shares = Money::split(10000, 2500, 5500, 2000);
    $half = Money::proportion($shares, 5000, 10000);
    expect(array_sum($half))->toBe(5000)->and($half['hub'])->toBe(2750);
    expect(Money::proportion($shares, 10000, 10000))->toBe($shares);
    $third = Money::proportion($shares, 3333, 10000);
    expect(array_sum($third))->toBe(3333);
});

it('formats rand amounts', function (): void {
    expect(Money::format(123456))->toBe('R1 234.56')->and(Money::format(-50))->toBe('-R0.50');
});
