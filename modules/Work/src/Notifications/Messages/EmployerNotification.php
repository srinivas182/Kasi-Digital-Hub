<?php

declare(strict_types=1);

namespace Modules\Work\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/**
 * What an employer's team hears: new applications, interview answers, new messages and a weekly
 * reminder about applicants waiting.
 */
final class EmployerNotification extends KasiNotification
{
    public const KEY = 'employer_update';

    public const CATEGORY = 'jobs';

    public const WHATSAPP_TEMPLATE = 'kasihub_employer_update';

    public const KINDS = ['new_application', 'interview_confirmed', 'interview_reschedule', 'interview_declined', 'message', 'waiting', 'hire_confirmed', 'withdrawn'];

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link, private readonly ?string $dedupe = null) {}

    public function title(User $user): string
    {
        return $this->t($user, "notify.emp.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.emp.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function channels(): array
    {
        return ['in_app', 'email', 'whatsapp'];
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
