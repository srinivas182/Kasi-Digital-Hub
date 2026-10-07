<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Models\UserDevice;
use Modules\Core\Identity\Services\Authenticator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs the session out when its device was signed out remotely, and enforces
 * inactivity timeouts (shorter for staff, organisation and national roles).
 */
final class TrackDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $deviceId = $request->session()->get('device_id');
        $device = is_string($deviceId) ? UserDevice::query()->find($deviceId) : null;

        if ($device === null || $device->revoked_at !== null) {
            return $this->signOut($request, 'device_signed_out');
        }

        $idleLimit = (int) config('kasi.identity.idle_minutes.'.($user->two_factor_required ? 'staff' : 'citizen')) * 60;
        $lastActivity = (int) $request->session()->get('last_activity_at', now()->getTimestamp());

        if (now()->getTimestamp() - $lastActivity > $idleLimit) {
            return $this->signOut($request, 'idle');
        }

        $request->session()->put('last_activity_at', now()->getTimestamp());

        if ($device->last_seen_at === null || $device->last_seen_at->diffInMinutes(now()) >= 5) {
            $device->forceFill(['last_seen_at' => now(), 'ip_address' => $request->ip()])->save();
        }

        return $next($request);
    }

    private function signOut(Request $request, string $reason): Response
    {
        app(Authenticator::class)->logout($reason);

        if ($request->expectsJson()) {
            abort(401);
        }

        return redirect()->route('login', $reason === 'idle' ? ['expired' => 1] : [])->with('signed_out_reason', $reason);
    }
}
