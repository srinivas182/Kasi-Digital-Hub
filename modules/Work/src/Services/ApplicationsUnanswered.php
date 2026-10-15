<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Insights\HealthSignal;

/** Coordinator health signal (S20): counts only, per hub. */
final class ApplicationsUnanswered implements HealthSignal
{
    public function key(): string
    {
        return 'work.applications_waiting';
    }

    public function counts(array $hubIds): array
    {
        return array_map('intval', DB::table('work_applications')->join('users', 'users.id', '=', 'work_applications.user_id')->where('work_applications.stage', 'new')
            ->where('work_applications.created_at', '<', now()->subDays(5))->whereIn('users.home_hub_id', $hubIds)->groupBy('users.home_hub_id')
            ->selectRaw('users.home_hub_id as hub, count(*) as n')->pluck('n', 'hub')->all());
    }
}
