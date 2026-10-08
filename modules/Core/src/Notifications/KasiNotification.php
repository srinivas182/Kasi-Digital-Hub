<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Modules\Core\Identity\Models\User;

/**
 * Base class for every platform notification (the notification catalogue).
 *
 * Text comes from translation files in the recipient's language. WhatsApp messages use
 * pre-approved templates (WHATSAPP_TEMPLATE + whatsappBody(), listed in
 * docs/whatsapp-templates.md). In-app messages go to the "what changed and why" feed.
 */
abstract class KasiNotification
{
    /** Stable key, e.g. "document_verified". */
    public const KEY = '';

    /** One of kasi.notifications.categories. "security" ignores preferences and quiet hours. */
    public const CATEGORY = 'account';

    /** Portal the update belongs to in the feed. */
    public const MODULE = 'Hub';

    /** Approved WhatsApp template name (null: no WhatsApp for this notification). */
    public const WHATSAPP_TEMPLATE = null;

    /** Meta template category: utility | authentication | marketing. */
    public const WHATSAPP_CATEGORY = 'utility';

    /** @return list<string> Channels this notification may use (subject to preferences and consent). */
    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    abstract public function title(User $user): string;

    abstract public function body(User $user): string;

    /** Why this happened - shown as the cause in the updates feed. */
    public function cause(User $user): ?string
    {
        return null;
    }

    public function url(): ?string
    {
        return null;
    }

    /** Important messages fall back to SMS when WhatsApp fails. */
    public function important(): bool
    {
        return false;
    }

    /** Same key within the dedupe window = sent only once. */
    public function dedupeKey(): ?string
    {
        return null;
    }

    /** Send SMS to a different number (e.g. the old number after a phone change). */
    public function smsTo(User $user): string
    {
        return $user->phone;
    }

    /** English template text with {{1}}, {{2}}... placeholders, as submitted to Meta. */
    public static function whatsappBody(): ?string
    {
        return null;
    }

    /** @return list<string> Values for the template placeholders, in order. */
    public function whatsappParams(User $user): array
    {
        return [];
    }

    /**
     * Translate a key in the recipient's language.
     *
     * @param  array<string, string|int>  $replace
     */
    protected function t(User $user, string $key, array $replace = []): string
    {
        $text = __($key, $replace, $user->preferred_locale);

        return is_string($text) ? $text : $key;
    }
}
