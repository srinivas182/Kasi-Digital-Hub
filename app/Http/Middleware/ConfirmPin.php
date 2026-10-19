<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * S23: KasiHub staff confirm their PIN again before sensitive actions (role changes, suspensions, refunds,
 * approvals, bank changes, exports, account help, demo reset) - at most every 15 minutes - so an unattended
 * signed-in session can't be used for them.
 */
final class ConfirmPin
{
    public const MINUTES = 15;

    public const SESSION_KEY = 'pin_confirmed_at';

    public function handle(Request $request, Closure $next): Response
    {
        $at = (int) $request->session()->get(self::SESSION_KEY, 0);
        if ($at > 0 && now()->getTimestamp() - $at <= self::MINUTES * 60) {
            return $next($request);
        }
        // Come back to the page the action was started from (or the GET itself, e.g. an export).
        $back = $request->isMethod('GET') ? $request->fullUrl() : (string) url()->previous();
        $request->session()->put('pin_confirm_return', $back);

        return $request->expectsJson()
            ? response()->json(['message' => __('auth.confirm_pin.needed')], 423)
            : redirect()->route('pin.confirm')->with('status', __('auth.confirm_pin.needed'));
    }
}
