<?php

declare(strict_types=1);

namespace Modules\Learn\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Course review news for a provider's team: submitted, in KasiHub review, changes asked, published, unpublished. */
final class CourseNotification extends KasiNotification
{
    public const KEY = 'course_review';

    public const CATEGORY = 'learning';

    public const WHATSAPP_TEMPLATE = 'kasihub_course_review';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.course.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.course.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return $this->kind === 'unpublished';
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
