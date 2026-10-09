<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

use Modules\Core\Ai\Models\AiFeatureSetting;
use Modules\Core\Ai\Models\AiRequestLog;

/**
 * Switches and spending limits: per feature (admin), per person per day (calls), per hub per
 * month and platform-wide per month (cents). Exceeded limits fall back to "write it yourself".
 */
final class AiBudget
{
    public function enabled(string $feature): bool
    {
        $settings = AiFeatureSetting::query()->whereIn('feature', [AiFeatureSetting::ALL, $feature])->pluck('enabled', 'feature');

        return ($settings[AiFeatureSetting::ALL] ?? true) && ($settings[$feature] ?? true);
    }

    /** Why a call may not run now, or null if it may. */
    public function refusal(string $feature, ?string $userId, ?string $hubId): ?string
    {
        if (! $this->enabled($feature)) {
            return 'disabled';
        }

        $budgets = (array) config('kasi.ai.budgets');
        $month = now()->startOfMonth();

        if ($userId !== null && AiRequestLog::query()->where('user_id', $userId)->where('outcome', 'ok')->where('created_at', '>=', now()->subDay())->count() >= (int) $budgets['per_person_day_calls']) {
            return 'budget';
        }

        if ($hubId !== null && $this->spent(['hub_id' => $hubId], $month) >= (int) $budgets['per_hub_month_cents']) {
            return 'budget';
        }

        $featureCap = AiFeatureSetting::query()->whereKey($feature)->value('monthly_budget_cents');
        if ($featureCap !== null && $this->spent(['feature' => $feature], $month) >= (int) $featureCap) {
            return 'budget';
        }

        if ($this->spent([], $month) >= (int) $budgets['platform_month_cents']) {
            return 'budget';
        }

        return null;
    }

    public function cost(string $tier, int $inputTokens, int $outputTokens): int
    {
        $price = (array) config("kasi.ai.price_cents_per_million.{$tier}");

        return (int) ceil(($inputTokens * (int) ($price['input'] ?? 0) + $outputTokens * (int) ($price['output'] ?? 0)) / 1_000_000);
    }

    /** @param array<string, string> $where */
    private function spent(array $where, \DateTimeInterface $since): int
    {
        return (int) AiRequestLog::query()->where($where)->where('created_at', '>=', $since)->sum('cost_cents');
    }
}
