<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Http\Controllers\Auth\Concerns\InteractsWithAuthFlow;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Identity\Services\OtpService;

/**
 * Step 2: the one-time SMS code (sign-in on a new device, sign-up, or forgotten PIN).
 */
final class CodeController
{
    use InteractsWithAuthFlow;

    public function __construct(private readonly OtpService $otp, private readonly DeviceManager $devices) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $flow = $this->flow($request);
        $phone = $this->flowPhone($request);

        if ($phone === null || ($flow['stage'] ?? null) !== 'code') {
            return to_route('login');
        }

        return Inertia::render('Core/Auth/Code', [
            'phone' => $this->maskPhone($phone),
            'action' => route('login.code.verify'),
            'resendAction' => route('login.code.resend'),
            'demoCode' => $request->session()->get('demo_code'),
            'purpose' => $flow['purpose'] ?? 'login',
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $flow = $this->flow($request);
        $phone = $this->flowPhone($request);
        $purpose = is_string($flow['purpose'] ?? null) ? $flow['purpose'] : 'login';

        if ($phone === null || ($flow['stage'] ?? null) !== 'code') {
            return to_route('login');
        }

        if (! $this->otp->verify($phone, $purpose, (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.code.wrong')]);
        }

        $this->updateFlow($request, ['verified' => true]);
        $user = $this->flowUser($request);

        if ($purpose === 'reset_pin') {
            if ($user === null) {
                return to_route('login');
            }
            $this->updateFlow($request, ['stage' => 'new_pin', 'user_id' => $user->id]);

            return to_route('login.new-pin');
        }

        if ($user === null) {
            $this->updateFlow($request, ['stage' => 'signup']);

            return to_route('signup');
        }

        if ($user->pin === null) {
            $this->updateFlow($request, ['stage' => 'new_pin', 'user_id' => $user->id]);

            return to_route('login.new-pin');
        }

        $this->updateFlow($request, ['stage' => 'pin_after_code', 'user_id' => $user->id]);

        return to_route('login.pin');
    }

    public function resend(Request $request): RedirectResponse
    {
        $flow = $this->flow($request);
        $phone = $this->flowPhone($request);

        if ($phone === null || ($flow['stage'] ?? null) !== 'code') {
            return to_route('login');
        }

        $purpose = is_string($flow['purpose'] ?? null) ? $flow['purpose'] : 'login';
        $result = $this->otp->request($phone, $purpose, $request->ip(), $this->devices->fingerprint());

        if (! $result['sent']) {
            return back()->withErrors(['code' => __('auth.code.'.$result['reason'], ['seconds' => $result['retry_after'] ?? 60])]);
        }

        return back()->with('demo_code', $result['demo_code'])->with('status', __('auth.code.resent'));
    }
}
