<?php

declare(strict_types=1);

namespace Modules\Admin\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/**
 * Tells a person their account was suspended or reactivated (security category: always by SMS).
 */
final class AccountStatusNotification extends KasiNotification
{
    public const KEY = 'account_status';

    public const CATEGORY = 'security';

    public const WHATSAPP_TEMPLATE = 'kasihub_account_status';

    public function __construct(private readonly bool $suspended) {}

    public function channels(): array
    {
        // A suspended person cannot open the app, so in-app is skipped for suspensions.
        return $this->suspended ? ['whatsapp', 'sms'] : ['in_app', 'whatsapp', 'sms'];
    }

    public function title(User $user): string
    {
        return $this->t($user, $this->suspended ? 'notify.account_suspended.title' : 'notify.account_reactivated.title');
    }

    public function body(User $user): string
    {
        return $this->t($user, $this->suspended ? 'notify.account_suspended.body' : 'notify.account_reactivated.body');
    }

    public static function whatsappBody(): string
    {
        return 'KasiHub account notice: {{1}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user).'. '.$this->body($user)];
    }
}
