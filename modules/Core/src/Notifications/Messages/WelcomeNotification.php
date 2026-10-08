<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class WelcomeNotification extends KasiNotification
{
    public const KEY = 'welcome';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_welcome';

    public function title(User $user): string
    {
        return $this->t($user, 'notify.welcome.title', ['name' => $user->displayName()]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.welcome.body');
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.welcome.cause');
    }

    public function url(): string
    {
        return '/home';
    }

    public function dedupeKey(): string
    {
        return 'welcome';
    }

    public static function whatsappBody(): string
    {
        return 'Welcome to KasiHub, {{1}}! Your account is ready. Find jobs, courses and business support - and visit your nearest Kasi Digital Hub if you need help.';
    }

    public function whatsappParams(User $user): array
    {
        return [$user->displayName()];
    }
}
