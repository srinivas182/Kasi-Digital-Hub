<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ConfirmPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/** Confirm your PIN again (S23). Five wrong tries in 15 minutes signs the person out. */
final class ConfirmPinController
{
    public function show(): Response
    {
        return Inertia::render('Core/ConfirmPin');
    }

    public function confirm(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $request->validate(['pin' => ['required', 'string', 'max:12']]);
        $key = 'confirm-pin:'.$user->id;
        if ($user->pin === null || ! Hash::check((string) $request->input('pin'), $user->pin)) {
            RateLimiter::hit($key, ConfirmPin::MINUTES * 60);
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $audit->record('auth.confirm_pin_locked', actor: $user);
                auth()->guard('web')->logout();
                $request->session()->invalidate();

                return redirect('/login')->withErrors(['phone' => __('auth.confirm_pin.signed_out')]);
            }

            return back()->withErrors(['pin' => __('auth.confirm_pin.wrong')]);
        }
        RateLimiter::clear($key);
        $request->session()->put(ConfirmPin::SESSION_KEY, now()->getTimestamp());
        $audit->record('auth.pin_confirmed', actor: $user);

        return redirect()->to((string) $request->session()->pull('pin_confirm_return', '/admin'));
    }
}
