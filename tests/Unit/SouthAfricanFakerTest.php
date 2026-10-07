<?php

declare(strict_types=1);

use App\Support\Demo\SouthAfricanFaker;

it('generates ID numbers that are SA-shaped but always fail the Luhn check', function (): void {
    $faker = new SouthAfricanFaker(seed: 2026);

    foreach (range(1, 500) as $ignored) {
        $id = $faker->invalidIdNumber();

        expect($id)->toMatch('/^\d{13}$/')
            ->and(SouthAfricanFaker::passesLuhn($id))->toBeFalse();
    }
});

it('validates a known-good Luhn number', function (): void {
    expect(SouthAfricanFaker::passesLuhn('79927398713'))->toBeTrue();
});

it('generates +27 mobile numbers', function (): void {
    expect((new SouthAfricanFaker(seed: 1))->mobileNumber())->toMatch('/^\+27[6-8]\d{8}$/');
});

it('is deterministic with a seed so demo stories are repeatable', function (): void {
    $a = new SouthAfricanFaker(seed: 42);
    $b = new SouthAfricanFaker(seed: 42);

    expect($a->fullName())->toBe($b->fullName())
        ->and($a->location())->toBe($b->location());
});
