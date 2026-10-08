<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Messages\SecurityAlertNotification;
use Modules\Core\Notifications\Notifier;

/**
 * Tells a person about important account changes, so a SIM swap or stolen PIN is noticed
 * quickly. Security alerts always go by SMS (plus WhatsApp when opted in) and ignore quiet hours.
 */
final readonly class SecurityAlerts
{
    public function __construct(private Notifier $notifier) {}

    /**
     * @param  array<string, string|int>  $replace
     */
    public function send(User $user, string $type, array $replace = [], ?string $phone = null): void
    {
        if (! in_array($type, SecurityAlertNotification::TYPES, true)) {
            return;
        }

        $this->notifier->send($user, new SecurityAlertNotification($type, (string) ($replace['device'] ?? ''), $phone));
    }
}
