<?php

declare(strict_types=1);

namespace Modules\HubOps\Console;

use Illuminate\Console\Command;
use Modules\HubOps\Models\HubVisit;

/**
 * Monthly (POPIA data minimisation): visits older than the retention period keep only the hub,
 * date, purpose and method - the person and the staff member are removed. Totals stay correct.
 */
final class AnonymiseVisitsCommand extends Command
{
    protected $signature = 'kasi:hub-ops:anonymise-visits';

    protected $description = 'Remove the person from hub visits older than the retention period';

    public function handle(): int
    {
        $cutoff = now('Africa/Johannesburg')->subMonths((int) config('kasi.hub_ops.visit_retention_months', 24))->toDateString();
        $count = HubVisit::query()->where('visit_date', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('checked_in_by'))
            ->update(['user_id' => null, 'checked_in_by' => null]);

        $this->info("Visits anonymised: {$count}");

        return self::SUCCESS;
    }
}
