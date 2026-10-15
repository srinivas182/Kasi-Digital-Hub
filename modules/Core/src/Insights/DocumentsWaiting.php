<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

use Illuminate\Support\Facades\DB;

/** Coordinator health signal (S20): counts only, per hub. */
final class DocumentsWaiting implements HealthSignal
{
    public function key(): string
    {
        return 'core.documents_waiting';
    }

    public function counts(array $hubIds): array
    {
        return array_map('intval', DB::table('documents')->join('users', 'users.id', '=', 'documents.user_id')->where('documents.status', 'uploaded')
            ->whereIn('users.home_hub_id', $hubIds)->groupBy('users.home_hub_id')->selectRaw('users.home_hub_id as hub, count(*) as n')->pluck('n', 'hub')->all());
    }
}
