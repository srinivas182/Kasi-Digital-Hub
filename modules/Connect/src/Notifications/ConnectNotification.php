<?php

declare(strict_types=1);

namespace Modules\Connect\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Mentoring news: requests, agreements, sessions and reminders, messages, vetting, and safeguarding alerts. */
final class ConnectNotification extends KasiNotification
{
    public const KEY = 'mentoring_update';

    public const CATEGORY = 'mentoring';

    public const WHATSAPP_TEMPLATE = 'kasihub_mentoring_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.connect.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.connect.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return in_array($this->kind, ['concern_urgent', 'mentor_paused'], true);
    }

    public static function whatsappBody(): string
    {
        return 'KasiLearn: {{1}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user)];
    }
}
