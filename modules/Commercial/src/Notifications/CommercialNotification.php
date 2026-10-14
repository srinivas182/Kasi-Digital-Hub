<?php

declare(strict_types=1);

namespace Modules\Commercial\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Finance news for hub owners: invoices, reminders, sales blocked, statements and payouts. */
final class CommercialNotification extends KasiNotification
{
    public const KEY = 'finance_update';

    public const CATEGORY = 'finance';

    public const WHATSAPP_TEMPLATE = 'kasihub_finance_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.commercial.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.commercial.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return in_array($this->kind, ['sales_blocked'], true);
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
