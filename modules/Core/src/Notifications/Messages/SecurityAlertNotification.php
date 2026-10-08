<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/**
 * Security alerts: always by SMS (cannot be switched off, ignore quiet hours), plus
 * WhatsApp when the person opted in.
 */
final class SecurityAlertNotification extends KasiNotification
{
    public const KEY = 'security_alert';

    public const CATEGORY = 'security';

    public const WHATSAPP_TEMPLATE = 'kasihub_security_alert';

    public const WHATSAPP_CATEGORY = 'utility';

    public const TYPES = ['new_device', 'pin_changed', 'phone_changed'];

    public function __construct(
        private readonly string $type,
        private readonly string $device = '',
        private readonly ?string $toPhone = null,
    ) {}

    public function channels(): array
    {
        // An alert to a previous number (after a phone change) is SMS only.
        return $this->toPhone !== null ? ['sms'] : ['in_app', 'whatsapp', 'sms'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.security.{$this->type}.title");
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.security.{$this->type}.body", ['device' => $this->device]);
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.security.cause');
    }

    public function url(): string
    {
        return '/account?tab=devices';
    }

    public function smsTo(User $user): string
    {
        return $this->toPhone ?? $user->phone;
    }

    public static function whatsappBody(): string
    {
        return 'KasiHub security alert: {{1}}. If this was not you, change your PIN now or visit your hub.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user)];
    }
}
