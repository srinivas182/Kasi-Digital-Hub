<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use App\Support\Format\SaFormat;

/**
 * Rules for South African numbers that may receive one-time codes.
 * Only mobile ranges are accepted; premium-rate and shared-cost ranges are blocked.
 */
final class PhoneNumbers
{
    /** Mobile prefixes after +27 (06x, 07x, 081-084). */
    private const MOBILE = '/^\+27(6\d|7[1-9]|8[1-4])\d{7}$/';

    /** Premium, shared-cost and toll-free ranges (086x, 080x, 090x). */
    private const BLOCKED = '/^\+27(86|80|90)/';

    public static function normalise(string $input): ?string
    {
        return SaFormat::normalisePhone($input);
    }

    public static function canReceiveCodes(string $e164): bool
    {
        return preg_match(self::MOBILE, $e164) === 1 && preg_match(self::BLOCKED, $e164) !== 1;
    }

    /** Number range used for anomaly detection, e.g. +27724 18. */
    public static function range(string $e164): string
    {
        return substr($e164, 0, 8);
    }
}
