<?php

declare(strict_types=1);

use Modules\Core\Identity\Services\AgePolicy;
use Modules\Core\Identity\Services\PhoneNumbers;
use Modules\Core\Identity\Services\PinPolicy;

it('accepts reasonable PINs and rejects weak ones', function (string $pin, ?string $problem): void {
    expect(PinPolicy::problem($pin))->toBe($problem);
})->with([
    ['24680', null],
    ['90817', null],
    ['12345', 'auth.pin.too_simple'],
    ['98765', 'auth.pin.too_simple'],
    ['77777', 'auth.pin.too_simple'],
    ['1234', 'auth.pin.invalid_format'],
    ['12a45', 'auth.pin.invalid_format'],
]);

it('classifies ages by the policy', function (int $years, string $band): void {
    expect(AgePolicy::classify(now()->subYears($years)->subDay()))->toBe($band);
})->with([[30, 'adult'], [18, 'adult'], [17, 'minor'], [16, 'minor'], [15, 'too_young']]);

it('only lets SA mobile numbers receive codes', function (string $phone, bool $ok): void {
    expect(PhoneNumbers::canReceiveCodes($phone))->toBe($ok);
})->with([
    ['+27724183390', true], ['+27601234567', true], ['+27821234567', true],
    ['+27111234567', false], ['+27861234567', false], ['+27801234567', false], ['+27851234567', false],
]);
