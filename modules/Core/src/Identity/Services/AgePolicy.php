<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Applies the platform age policy (config/kasi.php 'age').
 */
final class AgePolicy
{
    public const ADULT = 'adult';

    public const MINOR = 'minor';

    public const TOO_YOUNG = 'too_young';

    public static function classify(DateTimeInterface $dateOfBirth): string
    {
        $age = (int) CarbonImmutable::instance($dateOfBirth)->diffInYears(CarbonImmutable::now());

        if ($age >= (int) config('kasi.age.full_access_age')) {
            return self::ADULT;
        }

        return $age >= (int) config('kasi.age.minor_min_age') ? self::MINOR : self::TOO_YOUNG;
    }
}
