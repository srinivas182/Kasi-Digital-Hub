<?php

declare(strict_types=1);

namespace Modules\Learn\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Learners' news: enrolment open (saved courses), assignment assessed, course completed, updated course available; and new submissions for assessors. */
final class LearnerNotification extends KasiNotification
{
    public const KEY = 'learning_update';

    public const CATEGORY = 'learning';

    public const WHATSAPP_TEMPLATE = 'kasihub_learning_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.learn.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.learn.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return $this->kind === 'completed';
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
