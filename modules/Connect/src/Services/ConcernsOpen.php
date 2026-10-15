<?php

declare(strict_types=1);

namespace Modules\Connect\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Insights\HealthSignal;

/** Coordinator health signal (S20): counts only, per hub. */
final class ConcernsOpen implements HealthSignal
{
    public function key(): string
    {
        return 'connect.concerns_open';
    }

    public function counts(array $hubIds): array
    {
        return array_map('intval', DB::table('connect_concerns')->join('users', 'users.id', '=', 'connect_concerns.reporter_id')->where('connect_concerns.status', 'open')
            ->whereIn('users.home_hub_id', $hubIds)->groupBy('users.home_hub_id')->selectRaw('users.home_hub_id as hub, count(*) as n')->pluck('n', 'hub')->all());
    }
}
