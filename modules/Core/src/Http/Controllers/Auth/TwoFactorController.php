<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Http\Controllers\Auth\Concerns\InteractsWithAuthFlow;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\Authenticator;
use Modules\Core\Identity\Services\TwoFactorService;
use PragmaRX\Google2FA\Google2FA;

/**
 * Second step for staff, organisation and national roles: authenticator app setup
 * (QR code + backup codes) and the sign-in challenge.
 */
final class TwoFactorController
{
    use InteractsWithAuthFlow;

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function setup(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        if ($this->twoFactor->isConfirmed($user)) {
            return to_route('two-factor.challenge');
        }

        $secret = $this->twoFactor->begin($user);

        return Inertia::render('Core/Auth/TwoFactorSetup', [
            'qrSvg' => $this->twoFactor->qrCodeSvg($user, $secret),
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'demoCode' => config('kasi.demo.enabled') === true ? app(Google2FA::class)->getCurrentOtp($secret) : null,
        ]);
    }

    public function confirm(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $codes = $this->twoFactor->confirm($user, (string) $request->input('code'));

        if ($codes === null) {
            return back()->withErrors(['code' => __('auth.two_factor.wrong')]);
        }

        $audit->record('auth.two_factor_enabled', $user);
        $this->updateFlow($request, ['stage' => 'two_factor_codes']);

        return to_route('two-factor.recovery-codes')->with('recovery_codes', $codes);
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if ($this->pendingUser($request) === null || ! is_array($codes)) {
            return to_route('login');
        }

        return Inertia::render('Core/Auth/RecoveryCodes', ['codes' => $codes]);
    }

    public function finish(Request $request, Authenticator $authenticator): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null || ($this->flow($request)['stage'] ?? null) !== 'two_factor_codes') {
            return to_route('login');
        }

        $authenticator->login($user, (bool) ($this->flow($request)['remember'] ?? false));

        return redirect()->intended(route('hub.home'));
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $record = $user->twoFactor()->first();

        return Inertia::render('Core/Auth/TwoFactorChallenge', [
            'demoCode' => config('kasi.demo.enabled') === true && $record !== null ? app(Google2FA::class)->getCurrentOtp($record->secret) : null,
        ]);
    }

    public function verify(Request $request, AuditLogger $audit, Authenticator $authenticator): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => __('auth.code.rate_limited', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! $this->twoFactor->verify($user, (string) $request->input('code'))) {
            RateLimiter::hit($key, 900);
            $audit->record('auth.two_factor_failed', $user, 'failed');

            return back()->withErrors(['code' => __('auth.two_factor.wrong')]);
        }

        RateLimiter::clear($key);
        $authenticator->login($user, (bool) ($this->flow($request)['remember'] ?? false));

        return redirect()->intended(route('hub.home'));
    }

    private function pendingUser(Request $request): ?User
    {
        $flow = $this->flow($request);

        if (! in_array($flow['stage'] ?? null, ['two_factor', 'two_factor_codes'], true)) {
            return null;
        }

        $user = $this->flowUser($request);

        return $user?->two_factor_required ? $user : null;
    }
}
