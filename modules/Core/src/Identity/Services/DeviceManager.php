<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Models\UserDevice;

/**
 * Remembered devices and per-device sessions. A remembered device lets a person
 * sign in with phone + PIN (no SMS) for 30 days. Devices can be signed out remotely.
 */
final readonly class DeviceManager
{
    public const COOKIE = 'kasi_device';

    /**
     * The current request is resolved on every call (never stored), so the service is
     * safe to reuse across requests - e.g. in cached controllers and under Octane.
     */
    private function request(): Request
    {
        return app('request');
    }

    /** Stable fingerprint of this browser for rate limiting (not identifying). */
    public const BROWSER_COOKIE = 'kasi_browser';

    /**
     * Identifies this browser for rate limiting: the remembered-device token, else an anonymous
     * browser id cookie (so the identical computers in a hub are told apart), else IP + user agent.
     */
    public function fingerprint(): string
    {
        foreach ([self::COOKIE, self::BROWSER_COOKIE] as $cookie) {
            $token = $this->request()->cookie($cookie);
            if (is_string($token) && $token !== '') {
                return hash('sha256', $cookie.'|'.$token);
            }
        }

        return hash('sha256', $this->request()->ip().'|'.$this->request()->userAgent());
    }

    /** Give the browser an anonymous id (no personal data) the first time it opens sign-in. */
    public function ensureBrowserId(): void
    {
        if (! is_string($this->request()->cookie(self::BROWSER_COOKIE))) {
            Cookie::queue(Cookie::make(self::BROWSER_COOKIE, Str::random(40), 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));
        }
    }

    public function rememberedDeviceFor(User $user): ?UserDevice
    {
        $token = $this->request()->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $device = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $device?->isRemembered() ? $device : null;
    }

    /**
     * Record the device for the new session. Returns whether this device was new.
     */
    public function register(User $user, bool $remember): bool
    {
        $existing = $this->rememberedDeviceFor($user);
        $sessionId = $this->request()->session()->getId();

        if ($existing !== null) {
            $existing->update(['session_id' => $sessionId, 'last_seen_at' => now(), 'ip_address' => $this->request()->ip()]);
            $this->request()->session()->put('device_id', $existing->id);

            return false;
        }

        $token = $remember ? Str::random(64) : null;

        $device = UserDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => $token !== null ? hash('sha256', $token) : null,
            'session_id' => $sessionId,
            'name' => $this->describe((string) $this->request()->userAgent()),
            'ip_address' => $this->request()->ip(),
            'last_seen_at' => now(),
            'remembered_until' => $remember ? now()->addDays((int) config('kasi.identity.remember_device_days')) : null,
        ]);

        $this->request()->session()->put('device_id', $device->id);

        if ($token !== null) {
            Cookie::queue(Cookie::make(self::COOKIE, $token, (int) config('kasi.identity.remember_device_days') * 1440, httpOnly: true, sameSite: 'lax'));
        }

        return true;
    }

    public function revoke(UserDevice $device): void
    {
        $device->update(['revoked_at' => now(), 'remembered_until' => null]);

        if ($device->session_id !== null) {
            $this->request()->session()->getHandler()->destroy($device->session_id);
        }
    }

    public function revokeAllExcept(User $user, ?string $keepDeviceId): int
    {
        $devices = UserDevice::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->when($keepDeviceId !== null, fn ($query) => $query->whereKeyNot($keepDeviceId))
            ->get();

        $devices->each(fn (UserDevice $device) => $this->revoke($device));

        return $devices->count();
    }

    /** Human-readable device name from the user agent, e.g. "Chrome on Android". */
    public function describe(string $userAgent): string
    {
        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iPhone/iPad',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'Browser',
        };

        return "{$browser} on {$os}";
    }
}
