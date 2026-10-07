<?php

declare(strict_types=1);

use App\Support\Format\SaFormat;

// Shared with resources/js/__tests__/format.test.ts so PHP and TypeScript always agree.
$cases = json_decode((string) file_get_contents(__DIR__.'/../fixtures/format-cases.json'), true);

it('formats money', function (int $cents, bool $wholeRands, string $expected): void {
    expect(SaFormat::money($cents, $wholeRands))->toBe($expected);
})->with($cases['money']);

it('normalises and formats phone numbers', function (string $input, ?string $normalised, ?string $display): void {
    expect(SaFormat::normalisePhone($input))->toBe($normalised);

    if ($normalised !== null) {
        expect(SaFormat::phone($normalised))->toBe($display);
    }
})->with($cases['phones']);

it('formats dates in SAST', function (string $iso, string $date, string $dateTime): void {
    expect(SaFormat::date($iso))->toBe($date)
        ->and(SaFormat::dateTime($iso))->toBe($dateTime);
})->with($cases['dates']);
