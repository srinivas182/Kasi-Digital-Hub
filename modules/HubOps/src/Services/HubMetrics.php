<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Models\HubVisit;

/**
 * Hub performance numbers for a date range (South African dates, inclusive).
 */
final class HubMetrics
{
    /**
     * @return array{
     *   totals: array{visits: int, people: int, walkIns: int, newMembers: int, documentsVerified: int, events: int, signedUp: int, attended: int},
     *   byDay: list<array{date: string, visits: int}>,
     *   purposes: array<string, int>
     * }
     */
    public function forHub(Hub $hub, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $visits = HubVisit::query()->where('hub_id', $hub->id)->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()]);

        $perDay = (clone $visits)->selectRaw('visit_date, count(*) as total')->groupBy('visit_date')->pluck('total', 'visit_date')
            ->mapWithKeys(static fn ($total, $date): array => [substr((string) $date, 0, 10) => (int) $total]);

        $byDay = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $byDay[] = ['date' => $day->toDateString(), 'visits' => $perDay[$day->toDateString()] ?? 0];
        }

        [$start, $end] = $this->utcRange($from, $to);
        $events = HubEvent::query()->where('hub_id', $hub->id)->where('status', 'scheduled')->whereBetween('starts_at', [$start, $end]);
        $registrations = EventRegistration::query()->whereIn('event_id', (clone $events)->select('id'))->where('status', EventRegistration::REGISTERED);

        return [
            'totals' => [
                'visits' => (clone $visits)->count(),
                'people' => (clone $visits)->whereNotNull('user_id')->distinct()->count('user_id'),
                'walkIns' => (clone $visits)->whereNull('user_id')->count(),
                'newMembers' => User::query()->where('home_hub_id', $hub->id)->whereBetween('created_at', [$start, $end])->count(),
                'documentsVerified' => Document::query()->where('status', Document::VERIFIED)->whereBetween('verified_at', [$start, $end])
                    ->whereIn('user_id', User::query()->where('home_hub_id', $hub->id)->select('id'))->count(),
                'events' => (clone $events)->count(),
                'signedUp' => (clone $registrations)->count(),
                'attended' => (clone $registrations)->whereNotNull('attended_at')->count(),
            ],
            'byDay' => $byDay,
            'purposes' => array_map('intval', (clone $visits)->selectRaw('purpose, count(*) as total')->groupBy('purpose')->pluck('total', 'purpose')->all()),
        ];
    }

    /**
     * One row per hub, for coordinators comparing the hubs in their area.
     *
     * @param  iterable<Hub>  $hubs
     * @return list<array{id: string, name: string, visits: int, people: int, newMembers: int, events: int, attendanceRate: int|null}>
     */
    public function compare(iterable $hubs, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        foreach ($hubs as $hub) {
            $t = $this->forHub($hub, $from, $to)['totals'];
            $rows[] = [
                'id' => $hub->id,
                'name' => $hub->name,
                'visits' => $t['visits'],
                'people' => $t['people'],
                'newMembers' => $t['newMembers'],
                'events' => $t['events'],
                'attendanceRate' => $t['signedUp'] > 0 ? (int) round(100 * $t['attended'] / $t['signedUp']) : null,
            ];
        }

        return $rows;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function utcRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            CarbonImmutable::parse($from->toDateString(), 'Africa/Johannesburg')->startOfDay()->utc(),
            CarbonImmutable::parse($to->toDateString(), 'Africa/Johannesburg')->endOfDay()->utc(),
        ];
    }
}
