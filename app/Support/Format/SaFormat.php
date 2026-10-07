<?php

declare(strict_types=1);

namespace App\Support\Format;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * South African formatting. Must stay in step with resources/js/lib/format.ts;
 * both are tested against tests/fixtures/format-cases.json.
 */
final class SaFormat
{
    private const NBSP = "\u{00A0}";

    private const TIME_ZONE = 'Africa/Johannesburg';

    /** R1 234.56 - amounts are always handled in cents to avoid rounding errors. */
    public static function money(int $cents, bool $wholeRands = false): string
    {
        $negative = $cents < 0;
        $abs = abs($cents);
        $grouped = number_format(intdiv($abs, 100), 0, '.', self::NBSP);
        $value = $wholeRands ? $grouped : $grouped.'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').'R'.$value;
    }

    /** Normalise a South African number to E.164 (+27XXXXXXXXX), or null if it is not valid. */
    public static function normalisePhone(string $input): ?string
    {
        $digits = (string) preg_replace('/[^\d+]/', '', $input);

        if (str_starts_with($digits, '+27')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0027')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '27') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[1-8]\d{8}$/', $digits) === 1 ? '+27'.$digits : null;
    }

    /** +27724183390 -> 072 418 3390 */
    public static function phone(string $e164): string
    {
        $normalised = self::normalisePhone($e164);

        if ($normalised === null) {
            return $e164;
        }

        $local = '0'.substr($normalised, 3);

        return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6);
    }

    /** 7 Oct 2026 (SAST) */
    public static function date(DateTimeInterface|string $value): string
    {
        return self::inSast($value)->format('j M Y');
    }

    /** 7 Oct 2026, 14:30 (SAST, 24-hour) */
    public static function dateTime(DateTimeInterface|string $value): string
    {
        return self::inSast($value)->format('j M Y, H:i');
    }

    private static function inSast(DateTimeInterface|string $value): DateTimeImmutable
    {
        $date = $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable($value);

        return $date->setTimezone(new DateTimeZone(self::TIME_ZONE));
    }
}
