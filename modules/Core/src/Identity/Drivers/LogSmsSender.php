<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Drivers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Identity\Contracts\SmsSender;

/**
 * Writes SMS messages to the log instead of sending them (local, CI and demo).
 * The last message per number is kept briefly so tests and demos can read it.
 */
final class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS (log driver)', ['to' => $phone, 'message' => $message]);
        Cache::put(self::cacheKey($phone), $message, now()->addMinutes(10));
    }

    public static function lastMessage(string $phone): ?string
    {
        $message = Cache::get(self::cacheKey($phone));

        return is_string($message) ? $message : null;
    }

    private static function cacheKey(string $phone): string
    {
        return 'kasi:sms:last:'.hash('sha256', $phone);
    }
}
