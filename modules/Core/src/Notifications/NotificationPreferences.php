<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Models\NotificationPreference;

/**
 * Who wants what, where. Defaults keep costs low: WhatsApp and email on, SMS only for
 * security and account messages, marketing off everywhere. Security can't be switched off.
 */
final class NotificationPreferences
{
    /** @return list<string> */
    public static function categories(): array
    {
        return array_values((array) config('kasi.notifications.categories'));
    }

    /** Channels a person can switch (in-app is always on: it is their updates feed). */
    public const SWITCHABLE = ['whatsapp', 'sms', 'email'];

    public static function default(string $category, string $channel): bool
    {
        return match (true) {
            $channel === 'in_app' => true,
            $category === 'marketing' => false,
            $channel === 'sms' => in_array($category, ['security', 'account'], true),
            default => true,
        };
    }

    public static function locked(string $category, string $channel): bool
    {
        return $channel === 'in_app' || ($category === 'security' && $channel === 'sms');
    }

    public function enabled(User $user, string $category, string $channel): bool
    {
        if (self::locked($category, $channel)) {
            return true;
        }

        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)->where('category', $category)->where('channel', $channel)
            ->value('enabled');

        return $stored === null ? self::default($category, $channel) : (bool) $stored;
    }

    /**
     * @return list<array{category: string, channels: array<string, array{enabled: bool, locked: bool}>}>
     */
    public function matrix(User $user): array
    {
        $stored = NotificationPreference::query()->where('user_id', $user->id)->get()
            ->mapWithKeys(fn (NotificationPreference $p): array => ["{$p->category}.{$p->channel}" => $p->enabled]);

        return array_map(fn (string $category): array => [
            'category' => $category,
            'channels' => collect(self::SWITCHABLE)->mapWithKeys(fn (string $channel): array => [$channel => [
                'enabled' => self::locked($category, $channel) ? true : (bool) ($stored["{$category}.{$channel}"] ?? self::default($category, $channel)),
                'locked' => self::locked($category, $channel),
            ]])->all(),
        ], self::categories());
    }

    /**
     * @param  array<string, array<string, bool>>  $choices  category => channel => enabled
     */
    public function update(User $user, array $choices): void
    {
        foreach ($choices as $category => $channels) {
            if (! in_array($category, self::categories(), true)) {
                continue;
            }

            foreach ($channels as $channel => $enabled) {
                if (! in_array($channel, self::SWITCHABLE, true) || self::locked($category, $channel)) {
                    continue;
                }

                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'category' => $category, 'channel' => $channel],
                    ['enabled' => (bool) $enabled],
                );
            }
        }
    }
}
