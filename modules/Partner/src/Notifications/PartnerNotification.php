<?php

declare(strict_types=1);

namespace Modules\Partner\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

/** Referral news for entrepreneurs and partners: new referral, stage changes, messages, reminders, outcomes, follow-ups. */
final class PartnerNotification extends KasiNotification
{
    public const KEY = 'partner_update';

    public const CATEGORY = 'business';

    public const WHATSAPP_TEMPLATE = 'kasihub_partner_update';

    /** @param array<string, string> $params */
    public function __construct(private readonly string $kind, private readonly array $params, private readonly string $link) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.partner.{$this->kind}.title", $this->params);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.partner.{$this->kind}.body", $this->params);
    }

    public function url(): string
    {
        return $this->link;
    }

    public function important(): bool
    {
        return in_array($this->kind, ['approved', 'no_response_partner'], true);
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
