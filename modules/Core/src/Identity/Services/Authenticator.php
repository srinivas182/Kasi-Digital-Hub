<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Identity\Models\User;

/**
 * Completes sign-in once every required step has passed, and handles sign-out.
 */
final readonly class Authenticator
{
    public function __construct(
        private DeviceManager $devices,
        private AuditLogger $audit,
        private SecurityAlerts $alerts,
    ) {}

    /**
     * The current request is resolved on every call (never stored), so the service is
     * safe to reuse across requests - e.g. in cached controllers and under Octane.
     */
    private function request(): Request
    {
        return app('request');
    }

    public function login(User $user, bool $rememberDevice): void
    {
        Auth::login($user);
        $this->request()->session()->regenerate();
        $this->request()->session()->forget('auth_flow');
        $this->request()->session()->put('last_activity_at', now()->getTimestamp());

        $isNewDevice = $this->devices->register($user, $rememberDevice);
        $previousLogin = $user->last_login_at;

        $user->forceFill(['last_login_at' => now(), 'pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();
        $this->audit->record('auth.login', $user, meta: ['new_device' => $isNewDevice, 'remembered' => $rememberDevice]);

        // Alert on a new device, except for the very first sign-in after sign-up.
        if ($isNewDevice && $previousLogin !== null) {
            $this->alerts->send($user, 'new_device', ['device' => $this->devices->describe((string) $this->request()->userAgent())]);
        }
    }

    public function logout(string $reason = 'user'): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->audit->record('auth.logout', $user, meta: ['reason' => $reason]);
        }

        Auth::guard('web')->logout();
        $this->request()->session()->invalidate();
        $this->request()->session()->regenerateToken();
    }
}
