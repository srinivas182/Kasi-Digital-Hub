<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Help requests: replies, safety escalations, account unlocked (S21). */
final class SupportNotification extends KasiNotification
{
    public const KEY = 'support_update';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_support_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.support.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.support.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return in_array($this->kind, ['safety'], true);
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
