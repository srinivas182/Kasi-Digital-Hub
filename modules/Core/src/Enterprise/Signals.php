<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

/** Gathers every portal's business signals (S27). */
final class Signals
{
    /** @return array<string, int|float|bool|string|null> */
    public static function for(string $businessId): array
    {
        $all = [];
        foreach (app()->tagged(BusinessSignals::TAG) as $source) {
            /** @var BusinessSignals $source */
            $all = [...$all, ...$source->signals($businessId)];
        }

        return $all;
    }
}
