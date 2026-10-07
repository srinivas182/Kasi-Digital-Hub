<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Identity\Models\User;

/**
 * Tells a person about important account changes, so a SIM swap or stolen PIN is
 * noticed quickly. Sent by SMS now; WhatsApp is added with the messaging service (S4).
 */
final readonly class SecurityAlerts
{
    private const MESSAGES = [
        'new_device' => 'New sign-in to your KasiHub account on :device. If this was not you, visit your hub or change your PIN now.',
        'pin_changed' => 'Your KasiHub PIN was changed. If this was not you, contact your hub immediately.',
        'phone_changed' => 'Your KasiHub account phone number was changed. If this was not you, contact your hub immediately.',
    ];

    public function __construct(private SmsSender $sms) {}

    /**
     * @param  array<string, string|int>  $replace
     */
    public function send(User $user, string $type, array $replace = [], ?string $phone = null): void
    {
        $message = self::MESSAGES[$type] ?? null;

        if ($message === null) {
            return;
        }

        foreach ($replace as $key => $value) {
            $message = str_replace(":{$key}", (string) $value, $message);
        }

        $this->sms->send($phone ?? $user->phone, $message);
    }
}
