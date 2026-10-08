<?php

declare(strict_types=1);

namespace Modules\Admin\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class OrganisationRejectedNotification extends KasiNotification
{
    public const KEY = 'organisation_rejected';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_organisation_rejected';

    public function __construct(private readonly string $organisation, private readonly string $reason) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'sms', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, 'notify.organisation_rejected.title', ['organisation' => $this->organisation]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.organisation_rejected.body', ['reason' => $this->reason]);
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.organisation_rejected.cause');
    }

    public function important(): bool
    {
        return true;
    }

    public static function whatsappBody(): string
    {
        return 'We could not verify {{1}} on KasiHub. Reason: {{2}}. Please contact us to complete the check.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->organisation, $this->reason];
    }
}
