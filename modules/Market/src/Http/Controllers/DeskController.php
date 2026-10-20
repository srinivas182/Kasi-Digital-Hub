<?php

declare(strict_types=1);

namespace Modules\Market\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Market\Models\Order;
use Modules\Market\Services\Ordering;

/** The hub's KasiMarket collection desk (S26): parcels expected, at the hub, handed over by code, uncollected. */
final class DeskController
{
    public function __construct(private readonly Ordering $ordering) {}

    public function index(Request $request): Response
    {
        [, $hubId, $manager] = $this->staff($request);
        $orders = Order::query()->with('shop')->where('collection_hub_id', $hubId)->whereIn('status', ['accepted', 'ready', 'at_hub', 'uncollected'])
            ->latest('updated_at')->limit(200)->get();
        $row = static fn (Order $o): array => ['id' => $o->id, 'number' => $o->number, 'shop' => $o->shop->name, 'status' => $o->status,
            'atHub' => $o->getAttribute('at_hub_at') !== null ? (string) $o->getAttribute('at_hub_at') : null];
        $point = DB::table('market_collection_points')->where('hub_id', $hubId)->first();

        return Inertia::render('Market/Desk', [
            'expected' => $orders->whereIn('status', ['accepted', 'ready'])->values()->map($row),
            'here' => $orders->where('status', 'at_hub')->values()->map($row),
            'uncollected' => $orders->where('status', 'uncollected')->values()->map($row),
            'point' => ['enabled' => (bool) ($point->enabled ?? false), 'hours' => $point->hours ?? ''],
            'canManage' => $manager,
        ]);
    }

    public function checkIn(Request $request, Order $order): RedirectResponse
    {
        [$user, $hubId] = $this->staff($request);
        abort_unless($order->getAttribute('collection_hub_id') === $hubId, 404);

        return $this->attempt(fn () => $this->ordering->checkInAtHub($order, $user), 'market.desk.checked_in');
    }

    public function handOver(Request $request, Order $order): RedirectResponse
    {
        [$user, $hubId] = $this->staff($request);
        abort_unless($order->getAttribute('collection_hub_id') === $hubId, 404);
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        return $this->attempt(fn () => $this->ordering->handOver($order, $user, $data['code']), 'market.desk.handed_over');
    }

    public function settings(Request $request): RedirectResponse
    {
        [, $hubId, $manager] = $this->staff($request);
        abort_unless($manager, 403);
        $data = $request->validate(['enabled' => ['boolean'], 'hours' => ['nullable', 'string', 'max:200']]);
        DB::table('market_collection_points')->updateOrInsert(['hub_id' => $hubId], ['enabled' => (bool) ($data['enabled'] ?? false), 'hours' => $data['hours'] ?? null,
            'updated_at' => now(), 'created_at' => now()]);

        return back()->with('status', __('work.saved'));
    }

    /** @return array{0: User, 1: string, 2: bool} staff member, their hub, whether they manage it */
    private function staff(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $roles = RoleAssignment::query()->where('user_id', $user->id)->where('scope_type', 'hub')->whereIn('role', ['hub_owner', 'hub_manager', 'hub_facilitator'])->get();
        abort_if($roles->isEmpty(), 403);
        $hubId = (string) $roles->first()->scope_id;

        return [$user, $hubId, $roles->whereIn('role', ['hub_owner', 'hub_manager'])->isNotEmpty()];
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['desk' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }
}
