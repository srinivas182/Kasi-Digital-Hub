<?php

declare(strict_types=1);

namespace Modules\Work\Notifications\Messages;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** At most one a day: new jobs that match the person well. */
final class JobAlertNotification extends KasiNotification
{
    public const KEY = 'job_alert';

    public const CATEGORY = 'jobs';

    public const WHATSAPP_TEMPLATE = 'kasihub_job_alert';

    /** @param list<string> $titles */
    public function __construct(private readonly int $count, private readonly array $titles) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp'];
    }

    public function title(User $user): string
    {
        return $this->t($user, 'notify.job_alert.title', ['count' => $this->count]);
    }

    public function body(User $user): string
    {
        return implode(', ', array_slice($this->titles, 0, 3)).($this->count > 3 ? ' ...' : '');
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.job_alert.cause');
    }

    public function url(): string
    {
        return '/work/matches';
    }

    public function dedupeKey(): string
    {
        return 'job-alert:'.now('Africa/Johannesburg')->toDateString();
    }

    public static function whatsappBody(): string
    {
        return '{{1}} new jobs match your KasiWork profile: {{2}}. Open KasiHub to see them. Reply STOP to stop job alerts.';
    }

    public function whatsappParams(User $user): array
    {
        return [(string) $this->count, $this->body($user)];
    }
}
