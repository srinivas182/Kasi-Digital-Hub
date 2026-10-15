<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Insights\HealthSignal;

/** Coordinator health signal (S20): counts only, per hub. */
final class OverdueInvoices implements HealthSignal
{
    public function key(): string
    {
        return 'commercial.overdue_invoices';
    }

    public function counts(array $hubIds): array
    {
        return array_map('intval', DB::table('com_invoices')->where('status', 'open')->where('due_on', '<', now()->toDateString())->whereIn('hub_id', $hubIds)
            ->groupBy('hub_id')->selectRaw('hub_id as hub, count(*) as n')->pluck('n', 'hub')->all());
    }
}
