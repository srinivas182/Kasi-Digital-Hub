<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class RoleAssignedNotification extends KasiNotification
{
    public const KEY = 'role_assigned';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_role_assigned';

    public function __construct(private readonly string $roleLabel, private readonly string $where) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.role_assigned.title', ['role' => $this->roleLabel]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.role_assigned.body', ['role' => $this->roleLabel, 'where' => $this->where]);
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.role_assigned.cause');
    }

    public function url(): string
    {
        return '/home';
    }

    public static function whatsappBody(): string
    {
        return 'You now have the {{1}} role on KasiHub ({{2}}). Sign in to see your new tools.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->roleLabel, $this->where];
    }
}
