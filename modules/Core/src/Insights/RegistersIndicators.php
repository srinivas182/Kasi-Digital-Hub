<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

use Illuminate\Contracts\Foundation\Application;

/** Helper for service providers: bind and tag a portal's indicators (ADR-028). */
final class RegistersIndicators
{
    /** @param list<Indicator> $indicators */
    public static function add(Application $app, array $indicators): void
    {
        $ids = [];
        foreach ($indicators as $indicator) {
            $id = 'kasi.indicator.'.$indicator->key();
            $app->instance($id, $indicator);
            $ids[] = $id;
        }
        $app->tag($ids, Indicator::TAG);
    }
}
