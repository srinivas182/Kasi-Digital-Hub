<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Http\Controllers\Auth\Concerns\InteractsWithAuthFlow;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\Authenticator;
use Modules\Core\Identity\Services\PinPolicy;
use Modules\Core\Identity\Services\SecurityAlerts;

/**
 * Choose a new PIN after a forgotten PIN (verified by SMS code).
 */
final class NewPinController
{
    use InteractsWithAuthFlow;

    public function show(Request $request): Response|RedirectResponse
    {
        if (($this->flow($request)['stage'] ?? null) !== 'new_pin' || ($this->flow($request)['verified'] ?? false) !== true) {
            return to_route('login');
        }

        return Inertia::render('Core/Auth/NewPin');
    }

    public function store(Request $request, AuditLogger $audit, SecurityAlerts $alerts, Authenticator $authenticator): RedirectResponse
    {
        $request->validate(['pin' => ['required', 'string', 'confirmed'], 'remember' => ['boolean']]);

        $user = $this->flowUser($request);

        if ($user === null || ($this->flow($request)['stage'] ?? null) !== 'new_pin' || ($this->flow($request)['verified'] ?? false) !== true) {
            return to_route('login');
        }

        if ($problem = PinPolicy::problem((string) $request->input('pin'))) {
            return back()->withErrors(['pin' => __($problem)]);
        }

        $user->forceFill(['pin' => $request->input('pin'), 'pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();
        $audit->record('auth.pin_reset', $user);
        $alerts->send($user, 'pin_changed');

        if ($user->two_factor_required) {
            $this->updateFlow($request, ['stage' => 'two_factor', 'user_id' => $user->id, 'remember' => $request->boolean('remember')]);

            return to_route($user->twoFactor?->confirmed_at !== null ? 'two-factor.challenge' : 'two-factor.setup');
        }

        $authenticator->login($user, $request->boolean('remember'));

        return redirect()->intended(route('hub.home'));
    }
}
