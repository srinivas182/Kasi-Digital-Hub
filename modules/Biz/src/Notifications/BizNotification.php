<?php

declare(strict_types=1);

namespace Modules\Biz\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** KasiBiz reminders for business owners (S29): deadlines and low stock. */
final class BizNotification extends KasiNotification
{
    public const KEY = 'biz_update';

    public const CATEGORY = 'business'; // a notification-settings category (config kasi.notifications.categories)

    public const WHATSAPP_TEMPLATE = 'kasihub_biz_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.biz.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.biz.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return in_array($this->kind, ['order_new'], true);
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
