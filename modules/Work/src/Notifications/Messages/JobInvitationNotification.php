<?php

declare(strict_types=1);

namespace Modules\Work\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;
use Modules\Work\Models\JobListing;

/** An employer invited the person to apply for a job. */
final class JobInvitationNotification extends KasiNotification
{
    public const KEY = 'job_invitation';

    public const CATEGORY = 'jobs';

    public const WHATSAPP_TEMPLATE = 'kasihub_job_invitation';

    public function __construct(private readonly JobListing $listing) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.job_invitation.title', ['employer' => $this->listing->organisation->displayName()]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.job_invitation.body', ['title' => $this->listing->title]);
    }

    public function url(): string
    {
        return '/work/matches?tab=invitations';
    }

    public function dedupeKey(): string
    {
        return 'job-invitation:'.$this->listing->id;
    }

    public static function whatsappBody(): string
    {
        return '{{1}} invited you to apply for "{{2}}" on KasiWork. Open KasiHub to accept or decline.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->listing->organisation->displayName(), $this->listing->title];
    }
}
