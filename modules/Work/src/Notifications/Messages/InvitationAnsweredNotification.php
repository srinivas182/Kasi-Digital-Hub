<?php

declare(strict_types=1);

namespace Modules\Work\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** The person accepted or declined an employer's invitation. */
final class InvitationAnsweredNotification extends KasiNotification
{
    public const KEY = 'invitation_answered';

    public const CATEGORY = 'jobs';

    public const WHATSAPP_TEMPLATE = 'kasihub_invitation_answered';

    public function __construct(private readonly string $name, private readonly string $job, private readonly bool $accepted, private readonly string $listingId) {}

    public function channels(): array
    {
        return ['in_app', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, $this->accepted ? 'notify.invitation_accepted.title' : 'notify.invitation_declined.title', ['name' => $this->name, 'title' => $this->job]);
    }

    public function body(User $user): string
    {
        return $this->t($user, $this->accepted ? 'notify.invitation_accepted.body' : 'notify.invitation_declined.body');
    }

    public function url(): string
    {
        return '/work/employer/listings/'.$this->listingId.'/candidates';
    }

    public static function whatsappBody(): string
    {
        return 'KasiWork: {{1}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user)];
    }
}
