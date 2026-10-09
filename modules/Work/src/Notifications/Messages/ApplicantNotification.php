<?php

declare(strict_types=1);

namespace Modules\Work\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/**
 * Everything a job seeker hears about their applications: stage changes (shortlisted, interview,
 * offer, hired, not successful), interviews, new messages, "applying is now open" for saved jobs,
 * hire confirmation and retention check-ins. One class, one template, many kinds.
 */
final class ApplicantNotification extends KasiNotification
{
    public const KEY = 'applicant_update';

    public const CATEGORY = 'jobs';

    public const WHATSAPP_TEMPLATE = 'kasihub_application_update';

    public const KINDS = ['shortlisted', 'interview', 'offer', 'hired', 'unsuccessful', 'interview_proposed', 'interview_cancelled',
        'interview_reminder', 'message', 'applying_open', 'confirm_hire', 'retention'];

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link, private readonly ?string $dedupe = null) {}

    public function title(User $user): string
    {
        return $this->t($user, "notify.app.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.app.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return in_array($this->kind, ['interview_proposed', 'offer', 'interview_reminder'], true);
    }

    public function dedupeKey(): ?string
    {
        return $this->dedupe;
    }

    public static function whatsappBody(): string
    {
        return 'KasiWork: {{1}} {{2}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user), $this->body($user)];
    }
}
