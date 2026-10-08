<?php

declare(strict_types=1);

namespace Modules\Admin\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class OrganisationVerifiedNotification extends KasiNotification
{
    public const KEY = 'organisation_verified';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_organisation_verified';

    public function __construct(private readonly string $organisation) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.organisation_verified.title', ['organisation' => $this->organisation]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.organisation_verified.body');
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.organisation_verified.cause');
    }

    public function url(): string
    {
        return '/home';
    }

    public static function whatsappBody(): string
    {
        return '{{1}} is now verified on KasiHub. You can start posting opportunities for young people.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->organisation];
    }
}
