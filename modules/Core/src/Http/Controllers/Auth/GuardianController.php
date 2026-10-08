<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Events\UserRegistered;
use Modules\Core\Http\Controllers\Auth\Concerns\InteractsWithAuthFlow;
use Modules\Core\Identity\Models\GuardianConsent;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\Authenticator;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Identity\Services\OtpService;
use Modules\Core\Identity\Services\PhoneNumbers;

/**
 * Guardian consent for 16-17 year-olds: the guardian's details, then a code sent to the
 * guardian's phone. Until verified, the account cannot be used.
 */
final class GuardianController
{
    use InteractsWithAuthFlow;

    public const RELATIONSHIPS = ['parent', 'grandparent', 'aunt_uncle', 'sibling', 'legal_guardian', 'other'];

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $stage = $this->flow($request)['stage'] ?? null;

        return Inertia::render('Core/Auth/Guardian', [
            'name' => $user->displayName(),
            'relationships' => self::RELATIONSHIPS,
            'codeSent' => $stage === 'guardian_code',
            'guardianPhone' => $stage === 'guardian_code' ? $this->maskPhone((string) ($this->flow($request)['guardian_phone'] ?? '')) : null,
            'demoCode' => $request->session()->get('demo_code'),
        ]);
    }

    public function store(Request $request, OtpService $otp, DeviceManager $devices): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $validated = $request->validate([
            'guardian_name' => ['required', 'string', 'max:160'],
            'guardian_phone' => ['required', 'string', 'max:20'],
            'relationship' => ['required', Rule::in(self::RELATIONSHIPS)],
        ]);

        $phone = PhoneNumbers::normalise($validated['guardian_phone']);

        if ($phone === null || ! PhoneNumbers::canReceiveCodes($phone) || $phone === $user->phone) {
            return back()->withErrors(['guardian_phone' => __('auth.guardian.invalid_phone')]);
        }

        $result = $otp->request($phone, 'guardian', $request->ip(), $devices->fingerprint());

        if (! $result['sent']) {
            return back()->withErrors(['guardian_phone' => __('auth.code.'.$result['reason'], ['seconds' => $result['retry_after'] ?? 60])]);
        }

        GuardianConsent::query()->where('user_id', $user->id)->whereNull('verified_at')->delete();
        $consent = GuardianConsent::query()->create([
            'user_id' => $user->id,
            'guardian_name' => $validated['guardian_name'],
            'guardian_phone' => $phone,
            'relationship' => $validated['relationship'],
        ]);

        $this->updateFlow($request, ['stage' => 'guardian_code', 'guardian_phone' => $phone, 'guardian_consent_id' => $consent->id]);

        return back()->with('demo_code', $result['demo_code']);
    }

    public function verify(Request $request, OtpService $otp, AuditLogger $audit, Authenticator $authenticator): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $user = $this->pendingUser($request);
        $flow = $this->flow($request);
        $phone = is_string($flow['guardian_phone'] ?? null) ? $flow['guardian_phone'] : null;

        if ($user === null || $phone === null || ($flow['stage'] ?? null) !== 'guardian_code') {
            return to_route('login');
        }

        if (! $otp->verify($phone, 'guardian', (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.code.wrong')]);
        }

        GuardianConsent::query()->whereKey($flow['guardian_consent_id'] ?? null)->update(['verified_at' => now()]);
        $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
        $audit->record('signup.guardian_verified', $user);
        event(new UserRegistered($user));

        $authenticator->login($user, false);

        return to_route('hub.home')->with('welcome', true);
    }

    private function pendingUser(Request $request): ?User
    {
        $user = $this->flowUser($request);

        return $user !== null && $user->status === User::STATUS_PENDING_GUARDIAN ? $user : null;
    }
}
