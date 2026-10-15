<?php

declare(strict_types=1);

namespace Modules\Region\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Messages from city and provincial coordinators to hub managers (S20). */
final class RegionNotification extends KasiNotification
{
    public const KEY = 'coordinator_message';

    public const CATEGORY = 'hub';

    public const WHATSAPP_TEMPLATE = 'kasihub_coordinator_message';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.region.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.region.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return false;
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
