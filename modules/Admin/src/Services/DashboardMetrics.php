<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Models\NotificationDelivery;
use Modules\Core\Platform\Models\Enquiry;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;

/**
 * Numbers for the admin overview, cached for 5 minutes.
 */
final class DashboardMetrics
{
    /**
     * @return array{kpis: array<string, int>, registrations: list<array{week: string, count: int}>, hubsByStatus: array<string, int>}
     */
    public function all(): array
    {
        /** @var array{kpis: array<string, int>, registrations: list<array{week: string, count: int}>, hubsByStatus: array<string, int>} */
        return Cache::remember('kasi:admin:dashboard', now()->addMinutes(5), fn (): array => [
            'kpis' => [
                'people' => User::query()->count(),
                'newThisWeek' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'hubsLive' => Hub::query()->where('status', 'live')->count(),
                'documentsWaiting' => Document::query()->where('status', Document::UPLOADED)->count(),
                'organisationsWaiting' => Organisation::query()->where('verification_status', 'pending')->count(),
                'enquiriesOpen' => Enquiry::query()->where('status', 'new')->count(),
                'failedMessages7d' => NotificationDelivery::query()->where('status', NotificationDelivery::FAILED)->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'registrations' => $this->registrationsByWeek(12),
            'hubsByStatus' => array_map('intval', Hub::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
        ]);
    }

    /**
     * @return list<array{week: string, count: int}>
     */
    private function registrationsByWeek(int $weeks): array
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);
        $dates = PlatformEventRecord::query()
            ->where('name', 'core.user.registered')
            ->where('occurred_at', '>=', $start)
            ->pluck('occurred_at');

        $counts = array_fill(0, $weeks, 0);
        foreach ($dates as $date) {
            $index = (int) floor($start->diffInDays($date) / 7);
            if ($index >= 0 && $index < $weeks) {
                $counts[$index]++;
            }
        }

        return array_map(static fn (int $i): array => ['week' => $start->addWeeks($i)->toDateString(), 'count' => $counts[$i]], range(0, $weeks - 1));
    }
}
