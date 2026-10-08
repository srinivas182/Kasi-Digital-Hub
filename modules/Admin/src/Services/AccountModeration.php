<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Modules\Admin\Events\AccountReactivated;
use Modules\Admin\Events\AccountSuspended;
use Modules\Admin\Notifications\Messages\AccountStatusNotification;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Notifications\Notifier;

/**
 * Suspend and reactivate accounts. Suspension signs the person out on every device at once.
 */
final readonly class AccountModeration
{
    public function __construct(
        private DeviceManager $devices,
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    public function suspend(User $user, User $by, string $reason): void
    {
        $user->forceFill(['status' => User::STATUS_SUSPENDED])->save();
        $signedOut = $this->devices->revokeAllExcept($user, null);

        $this->audit->record('account.suspended', $user, meta: ['reason' => $reason, 'devices_signed_out' => $signedOut], actor: $by);
        event(new AccountSuspended($user, $by->id, $reason));
        $this->notifier->send($user, new AccountStatusNotification(suspended: true));
    }

    public function reactivate(User $user, User $by, string $reason): void
    {
        $user->forceFill(['status' => User::STATUS_ACTIVE, 'pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();

        $this->audit->record('account.reactivated', $user, meta: ['reason' => $reason], actor: $by);
        event(new AccountReactivated($user, $by->id, $reason));
        $this->notifier->send($user, new AccountStatusNotification(suspended: false));
    }
}
