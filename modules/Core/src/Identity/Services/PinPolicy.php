<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

/**
 * 5-digit PIN rules: digits only, not all the same, not a straight sequence.
 */
final class PinPolicy
{
    /** @return string|null Translation key of the problem, or null when the PIN is acceptable. */
    public static function problem(string $pin): ?string
    {
        $length = (int) config('kasi.identity.pin.length');

        if (preg_match('/^\d{'.$length.'}$/', $pin) !== 1) {
            return 'auth.pin.invalid_format';
        }

        if (count(array_unique(str_split($pin))) === 1) {
            return 'auth.pin.too_simple';
        }

        $ascending = implode('', range((int) $pin[0], (int) $pin[0] + $length - 1));
        $descending = implode('', range((int) $pin[0], (int) $pin[0] - $length + 1));

        if ($pin === $ascending || $pin === $descending) {
            return 'auth.pin.too_simple';
        }

        return null;
    }
}
