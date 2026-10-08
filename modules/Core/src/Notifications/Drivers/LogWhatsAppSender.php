<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Drivers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Notifications\Contracts\WhatsAppSender;
use RuntimeException;

/**
 * Logs WhatsApp messages instead of sending them (local, CI, demo). Numbers listed in
 * kasi.drivers.whatsapp_fail_numbers are treated as undeliverable, to exercise the SMS fallback.
 */
final class LogWhatsAppSender implements WhatsAppSender
{
    public function send(string $phone, string $template, string $language, array $params): string
    {
        if (in_array($phone, (array) config('kasi.drivers.whatsapp_fail_numbers'), true)) {
            throw new RuntimeException('Recipient is not on WhatsApp (log driver test number).');
        }

        Log::info('WhatsApp (log driver)', compact('phone', 'template', 'language', 'params'));
        Cache::put('kasi:whatsapp:last:'.hash('sha256', $phone), ['template' => $template, 'params' => $params], now()->addMinutes(10));

        return 'log-'.Str::ulid();
    }

    /** @return array{template: string, params: list<string>}|null */
    public static function lastMessage(string $phone): ?array
    {
        /** @var array{template: string, params: list<string>}|null $message */
        $message = Cache::get('kasi:whatsapp:last:'.hash('sha256', $phone));

        return $message;
    }
}
