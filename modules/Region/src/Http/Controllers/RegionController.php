<?php

declare(strict_types=1);

namespace Modules\Region\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Insights\HealthSignal;
use Modules\Core\Insights\Insights;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Region\Notifications\RegionNotification;

/**
 * City and provincial coordinators (ADR-028): their hubs' outcomes (aggregates, suppressed), operational
 * health counts, activity-drop alerts, and messages to hub managers. No personal data.
 */
final class RegionController
{
    public const DROP = 0.6; // alert when this month's visitors fall below 60% of the 3-month average

    public function __construct(private readonly Insights $insights) {}

    public function index(Request $request): Response
    {
        [$user, $area, $hubIds] = $this->area($request);
        app(AuditLogger::class)->record('region.viewed', meta: ['area' => $area['label']], actor: $user);
        $now = CarbonImmutable::now('Africa/Johannesburg');
        [$from, $to] = [$now->startOfMonth(), $now];
        $totals = $this->insights->totals($hubIds, $from, $to);
        $visitors = $this->insights->totals($hubIds, $now->subMonths(3)->startOfMonth(), $now->subMonth()->endOfMonth(), ['hubs.visitors'])['hubs.visitors']['byHub'] ?? [];
        $signals = [];
        foreach (app()->tagged(HealthSignal::TAG) as $signal) {
            /** @var HealthSignal $signal */
            $signals[$signal->key()] = $signal->counts($hubIds);
        }

        $hubs = Hub::query()->whereIn('id', $hubIds)->orderBy('name')->get()->map(function (Hub $hub) use ($totals, $visitors, $signals): array {
            $thisMonth = (int) ($totals['hubs.visitors']['byHub'][$hub->id] ?? 0);
            $average = ((int) ($visitors[$hub->id] ?? 0)) / 3;
            $day = max(1, (int) now('Africa/Johannesburg')->day) / (int) now('Africa/Johannesburg')->daysInMonth; // pro-rata for a part month

            return [
                'id' => $hub->id, 'name' => $hub->name, 'status' => $hub->status, 'package' => $hub->package,
                'values' => array_map(static fn (array $t): int|string => Insights::cell((int) ($t['byHub'][$hub->id] ?? 0)), $totals),
                'drop' => $average >= Insights::MIN_CELL && $thisMonth < self::DROP * $average * $day,
                'health' => array_map(static fn (array $counts): int => (int) ($counts[$hub->id] ?? 0), $signals),
            ];
        });

        return Inertia::render('Region/Index', [
            'area' => $area, 'period' => $now->format('Y-m'),
            'totals' => array_map(static fn (array $t): int|string => Insights::cell($t['total']), $totals),
            'units' => array_map(static fn ($i): string => $i->unit(), $this->insights->indicators()),
            'hubs' => $hubs, 'signals' => array_keys($signals),
        ]);
    }

    public function message(Request $request, Hub $hub, Notifier $notifier): RedirectResponse
    {
        [$user, , $hubIds] = $this->area($request);
        abort_unless(in_array($hub->id, $hubIds, true), 404);
        $data = $request->validate(['message' => ['required', 'string', 'min:5', 'max:500']]);
        $managers = RoleAssignment::query()->whereIn('role', ['hub_manager', 'hub_owner'])->where('scope_type', 'hub')->where('scope_id', $hub->id)->pluck('user_id');
        User::query()->whereIn('id', $managers)->get()->each(fn (User $u) => $notifier->send($u, new RegionNotification('message', ['name' => $user->first_name, 'message' => $data['message']], '/hub')));
        app(AuditLogger::class)->record('region.message', meta: ['hub' => $hub->id], actor: $user);

        return back()->with('status', __('region.message.sent'));
    }

    /** @return array{0: User, 1: array{type: string, label: string}, 2: list<string>} */
    private function area(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $role = RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['provincial_coordinator', 'city_coordinator'])->orderBy('role', 'desc')->first();
        if ($role === null) {
            abort_unless(app(AccessResolver::class)->hasPermission($user, 'funder.admin'), 403);

            return [$user, ['type' => 'national', 'label' => __('region.national')], Insights::hubsIn()];
        }
        if ($role->scope_type === 'province') {
            return [$user, ['type' => 'province', 'label' => (string) DB::table('provinces')->where('id', $role->scope_id)->value('name')], Insights::hubsIn(provinceId: (int) $role->scope_id)];
        }

        return [$user, ['type' => 'municipality', 'label' => (string) DB::table('municipalities')->where('id', $role->scope_id)->value('name')], Insights::hubsIn(municipalityId: (int) $role->scope_id)];
    }
}
