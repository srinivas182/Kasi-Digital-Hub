<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\Models\AiFeatureSetting;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * AI budget alerts (S21): super admins hear when spend reaches 80% and 100% of the platform budget or a
 * feature's budget - once per level per month. At 100% the S8 budget check already pauses the feature.
 */
final readonly class AiAlerts
{
    public const LEVELS = [80, 100];

    public function __construct(private Notifier $notifier) {}

    public static function projection(int $spentCents): int
    {
        $day = max(1, (int) now()->day);

        return (int) round($spentCents / $day * (int) now()->daysInMonth);
    }

    public function check(): int
    {
        $period = now()->format('Y-m');
        $since = now()->startOfMonth();
        $scopes = ['platform' => [(int) config('kasi.ai.budgets.platform_month_cents'), (int) DB::table('ai_requests')->where('created_at', '>=', $since)->sum('cost_cents')]];
        foreach (AiFeatureSetting::query()->whereNotNull('monthly_budget_cents')->where('feature', '!=', AiFeatureSetting::ALL)->get() as $setting) {
            $scopes[$setting->feature] = [(int) $setting->monthly_budget_cents, (int) DB::table('ai_requests')->where('feature', $setting->feature)->where('created_at', '>=', $since)->sum('cost_cents')];
        }

        $sent = 0;
        foreach ($scopes as $scope => [$budget, $spent]) {
            foreach (self::LEVELS as $level) {
                if ($budget <= 0 || $spent * 100 < $budget * $level) {
                    continue;
                }
                if (DB::table('ai_budget_alerts')->insertOrIgnore(['scope' => $scope, 'period' => $period, 'level' => $level, 'created_at' => now()]) === 0) {
                    continue; // already told this month
                }
                User::query()->whereIn('id', RoleAssignment::query()->where('role', 'super_admin')->pluck('user_id'))->get()
                    ->each(fn (User $u) => $this->notifier->send($u, new SupportNotification('ai_budget_'.$level, ['scope' => $scope === 'platform' ? __('admin.ai.all_features') : $scope,
                        'spent' => 'R'.number_format($spent / 100, 2, '.', ' '), 'budget' => 'R'.number_format($budget / 100, 2, '.', ' ')], '/admin/ai')));
                $sent++;
            }
        }

        return $sent;
    }
}
