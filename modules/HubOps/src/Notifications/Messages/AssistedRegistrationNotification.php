<?php

declare(strict_types=1);

namespace Modules\HubOps\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/**
 * Tells a person that a facilitator registered them at a hub (security: always sent, by SMS too),
 * so a registration made without them is noticed.
 */
final class AssistedRegistrationNotification extends KasiNotification
{
    public const KEY = 'assisted_registration';

    public const CATEGORY = 'security';

    public const WHATSAPP_TEMPLATE = 'kasihub_assisted_registration';

    public function __construct(private readonly string $facilitator, private readonly string $hub) {}

    public function channels(): array
    {
        return ['in_app', 'sms'];
    }

    public function title(User $user): string
    {
        return $this->t($user, 'notify.assisted_registration.title', ['facilitator' => $this->facilitator, 'hub' => $this->hub]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.assisted_registration.body');
    }

    public static function whatsappBody(): string
    {
        return 'KasiHub: {{1}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user).'. '.$this->body($user)];
    }
}
