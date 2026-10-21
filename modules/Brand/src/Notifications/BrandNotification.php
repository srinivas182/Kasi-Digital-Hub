<?php

declare(strict_types=1);

namespace Modules\Brand\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** KasiBrand messages for owners (S31): website take-down and restore, post reminders. */
final class BrandNotification extends KasiNotification
{
    public const KEY = 'brand_update';

    public const CATEGORY = 'business'; // a notification-settings category (config kasi.notifications.categories)

    public const WHATSAPP_TEMPLATE = 'kasihub_brand_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.brand.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.brand.{$this->kind}.body", $this->params);
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
