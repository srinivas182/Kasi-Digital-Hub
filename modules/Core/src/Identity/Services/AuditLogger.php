<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;

/**
 * Writes the security and activity trail. Never log codes, PINs or secrets in meta.
 */
final readonly class AuditLogger
{
    /**
     * The current request is resolved on every call (never stored), so the service is
     * safe to reuse across requests - e.g. in cached controllers and under Octane.
     */
    private function request(): Request
    {
        return app('request');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(string $event, ?User $user = null, string $outcome = 'success', array $meta = [], ?User $actor = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'outcome' => $outcome,
            'ip_address' => $this->request()->ip(),
            'user_agent' => Str::limit((string) $this->request()->userAgent(), 250, ''),
            'meta' => $meta === [] ? null : $meta,
            'created_at' => now(),
        ]);
    }
}
