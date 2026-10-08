<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Http\Controllers\Auth\Concerns\InteractsWithAuthFlow;
use Modules\Core\Identity\Contracts\BotCheck;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\Authenticator;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Identity\Services\OtpService;
use Modules\Core\Identity\Services\PhoneNumbers;

/**
 * Step 1 (phone) and the PIN step of sign-in. Responses never reveal whether a
 * number is registered: unknown numbers and unknown devices both receive a code.
 */
final class SignInController
{
    use InteractsWithAuthFlow;

    public function __construct(
        private readonly OtpService $otp,
        private readonly DeviceManager $devices,
        private readonly AuditLogger $audit,
        private readonly Authenticator $authenticator,
    ) {}

    public function showPhone(Request $request): Response
    {
        $this->devices->ensureBrowserId();

        return Inertia::render('Core/Auth/Phone', [
            'expired' => $request->boolean('expired'),
            'signedOut' => $request->session()->get('signed_out_reason'),
        ]);
    }

    public function submitPhone(Request $request, BotCheck $botCheck): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20'], 'bot_token' => ['nullable', 'string', 'max:2048']]);

        $phone = PhoneNumbers::normalise((string) $request->input('phone'));

        if ($phone === null || ! PhoneNumbers::canReceiveCodes($phone)) {
            return back()->withErrors(['phone' => __('auth.phone.invalid')]);
        }

        if (! $botCheck->passes($request->string('bot_token')->toString() ?: null, $request->ip())) {
            return back()->withErrors(['phone' => __('auth.phone.bot_check_failed')]);
        }

        $request->session()->forget('auth_flow');
        $this->updateFlow($request, ['phone' => $phone]);

        $user = User::query()->where('phone', $phone)->first();

        if ($user !== null && $user->pin !== null && $this->devices->rememberedDeviceFor($user) !== null) {
            $this->updateFlow($request, ['stage' => 'pin', 'remember' => true]);

            return to_route('login.pin');
        }

        return $this->sendCode($request, $phone, 'login');
    }

    public function showPin(Request $request): Response|RedirectResponse
    {
        $stage = $this->flow($request)['stage'] ?? null;
        $phone = $this->flowPhone($request);

        if ($phone === null || ! in_array($stage, ['pin', 'pin_after_code'], true)) {
            return to_route('login');
        }

        $user = $this->flowUser($request);

        return Inertia::render('Core/Auth/Pin', [
            'phone' => $this->maskPhone($phone),
            'askRemember' => $stage === 'pin_after_code',
            'lockedMinutes' => $user?->isPinLocked() ? (int) ceil(now()->diffInMinutes($user->pin_locked_until)) : null,
        ]);
    }

    public function submitPin(Request $request): RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string'], 'remember' => ['boolean']]);

        $stage = $this->flow($request)['stage'] ?? null;
        $user = $this->flowUser($request);

        if ($user === null || ! in_array($stage, ['pin', 'pin_after_code'], true)) {
            return to_route('login');
        }

        if ($user->isPinLocked()) {
            return back()->withErrors(['pin' => __('auth.pin.locked', ['minutes' => (int) ceil(now()->diffInMinutes($user->pin_locked_until))])]);
        }

        if ($user->pin === null || ! Hash::check((string) $request->input('pin'), $user->pin)) {
            $attempts = $user->pin_failed_attempts + 1;
            $locked = $attempts >= (int) config('kasi.identity.pin.max_attempts');
            $user->forceFill([
                'pin_failed_attempts' => $locked ? 0 : $attempts,
                'pin_locked_until' => $locked ? now()->addMinutes((int) config('kasi.identity.pin.lockout_minutes')) : null,
            ])->save();
            $this->audit->record($locked ? 'auth.pin_locked' : 'auth.pin_failed', $user, 'failed');

            return back()->withErrors(['pin' => $locked
                ? __('auth.pin.locked', ['minutes' => (int) config('kasi.identity.pin.lockout_minutes')])
                : __('auth.pin.wrong', ['left' => (int) config('kasi.identity.pin.max_attempts') - $attempts])]);
        }

        if (! in_array($user->status, [User::STATUS_ACTIVE, User::STATUS_DELETION_REQUESTED], true)) {
            $this->audit->record('auth.login_blocked', $user, 'blocked', ['status' => $user->status]);

            return back()->withErrors(['pin' => __('auth.account.not_active')]);
        }

        $remember = $stage === 'pin' || $request->boolean('remember');

        if ($user->two_factor_required) {
            $this->updateFlow($request, ['stage' => 'two_factor', 'user_id' => $user->id, 'remember' => $remember]);

            return to_route($user->twoFactor?->confirmed_at !== null ? 'two-factor.challenge' : 'two-factor.setup');
        }

        $this->authenticator->login($user, $remember);

        return redirect()->intended(route('hub.home'));
    }

    public function forgotPin(Request $request): RedirectResponse
    {
        $phone = $this->flowPhone($request);

        if ($phone === null) {
            return to_route('login');
        }

        return $this->sendCode($request, $phone, 'reset_pin');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authenticator->logout();

        return to_route('login');
    }

    private function sendCode(Request $request, string $phone, string $purpose): RedirectResponse
    {
        $result = $this->otp->request($phone, $purpose, $request->ip(), $this->devices->fingerprint());

        if (! $result['sent']) {
            return back()->withErrors(['phone' => __('auth.code.'.$result['reason'], ['seconds' => $result['retry_after'] ?? 60])]);
        }

        $this->updateFlow($request, ['stage' => 'code', 'purpose' => $purpose, 'verified' => false]);

        return to_route('login.code')->with('demo_code', $result['demo_code']);
    }
}
