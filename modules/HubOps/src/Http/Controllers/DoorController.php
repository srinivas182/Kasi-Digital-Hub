<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Services\CheckInCodes;
use Modules\HubOps\Services\CheckIns;
use Modules\HubOps\Services\Qr;

/**
 * The door screen (no sign-in; opened with a secret link) and checking in by scanning it.
 * A valid scan is held for 15 minutes so people who still need to sign in don't lose it.
 */
final class DoorController
{
    private const SESSION = 'hubops.checkin';

    public function __construct(private readonly CheckInCodes $codes) {}

    public function door(Hub $hub, string $token): Response
    {
        $this->guardKiosk($hub, $token);

        return Inertia::render('HubOps/Door', ['hub' => ['name' => $hub->name], 'codeUrl' => route('kiosk.code', ['hub' => $hub->slug, 'token' => $token]), ...$this->payload($hub)]);
    }

    public function code(Hub $hub, string $token): JsonResponse
    {
        $this->guardKiosk($hub, $token);

        return response()->json($this->payload($hub))->header('Cache-Control', 'no-store');
    }

    public function scan(Request $request, Hub $hub): Response|RedirectResponse
    {
        abort_unless($hub->status === 'live', 404);

        if (! $this->codes->valid($hub, (string) $request->query('c', ''))) {
            return Inertia::render('HubOps/CheckIn/Expired', ['hub' => ['name' => $hub->name]]);
        }

        $request->session()->put(self::SESSION, ['hub' => $hub->id, 'until' => now()->addMinutes(15)->getTimestamp()]);

        // Not signed in? The sign-in screen brings them back here afterwards.
        return to_route('checkin.confirm');
    }

    public function confirm(Request $request, CheckIns $checkIns): Response|RedirectResponse
    {
        $hub = $this->pendingHub($request);
        if ($hub === null) {
            return to_route('hub.home');
        }

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('HubOps/CheckIn/Confirm', [
            'hub' => ['name' => $hub->name],
            'purposes' => HubVisit::PURPOSES,
            'alreadyToday' => $checkIns->visitedToday($hub, $user),
        ]);
    }

    public function store(Request $request, CheckIns $checkIns): RedirectResponse
    {
        $hub = $this->pendingHub($request);
        if ($hub === null) {
            return to_route('hub.home');
        }

        $validated = $request->validate(['purpose' => ['required', Rule::in(HubVisit::PURPOSES)]]);
        /** @var User $user */
        $user = $request->user();
        $checkIns->record($hub, $user, $validated['purpose'], 'qr');
        $request->session()->forget(self::SESSION);

        return to_route('hub.home')->with('status', __('hubops.checkin.welcome', ['hub' => $hub->name]));
    }

    private function pendingHub(Request $request): ?Hub
    {
        /** @var array{hub: string, until: int}|null $pending */
        $pending = $request->session()->get(self::SESSION);

        return $pending !== null && $pending['until'] >= now()->getTimestamp() ? Hub::query()->find($pending['hub']) : null;
    }

    private function guardKiosk(Hub $hub, string $token): void
    {
        abort_unless($hub->status === 'live' && $this->codes->kioskTokenValid($hub, $token), 404);
    }

    /** @return array{qr: string, url: string, secondsLeft: int} */
    private function payload(Hub $hub): array
    {
        $url = route('checkin.scan', ['hub' => $hub->slug, 'c' => $this->codes->current($hub)]);

        return ['qr' => Qr::svg($url, 360), 'url' => $url, 'secondsLeft' => $this->codes->secondsLeft()];
    }
}
