<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth\Concerns;

use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;

/**
 * Sign-in is a short multi-step flow (phone -> code -> PIN -> second step). Its state lives
 * in the session under "auth_flow" and is cleared on success or when the flow restarts.
 *
 * Keys: phone, stage, purpose, verified (bool), user_id, remember (bool).
 */
trait InteractsWithAuthFlow
{
    /**
     * @return array<string, mixed>
     */
    protected function flow(Request $request): array
    {
        $flow = $request->session()->get('auth_flow', []);

        return is_array($flow) ? $flow : [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function updateFlow(Request $request, array $values): void
    {
        $request->session()->put('auth_flow', [...$this->flow($request), ...$values]);
    }

    protected function flowPhone(Request $request): ?string
    {
        $phone = $this->flow($request)['phone'] ?? null;

        return is_string($phone) ? $phone : null;
    }

    protected function flowUser(Request $request): ?User
    {
        $id = $this->flow($request)['user_id'] ?? null;

        if (is_string($id)) {
            return User::query()->find($id);
        }

        $phone = $this->flowPhone($request);

        return $phone !== null ? User::query()->where('phone', $phone)->first() : null;
    }

    /** +27724183390 -> 072 *** 3390 */
    protected function maskPhone(string $e164): string
    {
        $local = '0'.substr($e164, 3);

        return substr($local, 0, 3).' *** '.substr($local, -4);
    }
}
