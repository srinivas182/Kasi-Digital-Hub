<?php

declare(strict_types=1);

namespace Modules\Partner\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Insights\HealthSignal;

/** Coordinator health signal (S20): counts only, per hub. */
final class ReferralsWaiting implements HealthSignal
{
    public function key(): string
    {
        return 'partner.referrals_waiting';
    }

    public function counts(array $hubIds): array
    {
        return array_map('intval', DB::table('partner_referrals')->join('users', 'users.id', '=', 'partner_referrals.sent_by')->where('partner_referrals.stage', 'new')
            ->where('partner_referrals.created_at', '<', now()->subDays(21))->whereIn('users.home_hub_id', $hubIds)->groupBy('users.home_hub_id')
            ->selectRaw('users.home_hub_id as hub, count(*) as n')->pluck('n', 'hub')->all());
    }
}
