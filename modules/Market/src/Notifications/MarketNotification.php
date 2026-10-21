<?php

declare(strict_types=1);

namespace Modules\Market\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** KasiMarket order news for traders and buyers (S25). */
final class MarketNotification extends KasiNotification
{
    public const KEY = 'market_update';

    public const CATEGORY = 'business'; // a notification-settings category (config kasi.notifications.categories)

    public const WHATSAPP_TEMPLATE = 'kasihub_market_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.market.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.market.{$this->kind}.body", $this->params);
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
