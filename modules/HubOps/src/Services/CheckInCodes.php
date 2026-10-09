<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Illuminate\Support\Str;
use Modules\Core\Structure\Models\Hub;

/**
 * The door QR code changes every 2 minutes, so a photo of it is useless from home.
 * Codes are an HMAC of the hub and the time window - nothing to store or clean up.
 *
 * The door screen itself opens with a secret link (no sign-in on a public screen);
 * managers can replace the link at any time.
 */
final class CheckInCodes
{
    public const WINDOW_SECONDS = 120;

    public function current(Hub $hub): string
    {
        return $this->forWindow($hub, $this->window());
    }

    /** Accepts the current and the previous window, so a scan just before the change still works. */
    public function valid(Hub $hub, string $code): bool
    {
        $window = $this->window();

        return hash_equals($this->forWindow($hub, $window), $code) || hash_equals($this->forWindow($hub, $window - 1), $code);
    }

    public function secondsLeft(): int
    {
        return self::WINDOW_SECONDS - (now()->getTimestamp() % self::WINDOW_SECONDS);
    }

    public function issueKioskToken(Hub $hub): string
    {
        $token = Str::random(40);
        $hub->forceFill(['kiosk_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public function kioskTokenValid(Hub $hub, string $token): bool
    {
        return $hub->kiosk_token_hash !== null && hash_equals($hub->kiosk_token_hash, hash('sha256', $token));
    }

    private function window(): int
    {
        return intdiv(now()->getTimestamp(), self::WINDOW_SECONDS);
    }

    private function forWindow(Hub $hub, int $window): string
    {
        return substr(hash_hmac('sha256', $hub->id.'|'.$window, (string) config('app.key')), 0, 12);
    }
}
