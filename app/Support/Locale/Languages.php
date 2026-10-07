<?php

declare(strict_types=1);

namespace App\Support\Locale;

/**
 * Interface languages available in the current environment.
 */
final class Languages
{
    public const COOKIE = 'kasi_locale';

    /**
     * @return array<string, array{name: string, native: string, draft: bool}>
     */
    public static function available(): array
    {
        /** @var array<string, array{name: string, native: string, draft: bool}> $languages */
        $languages = config('kasi.locale.languages', []);
        $showDrafts = config('kasi.locale.show_drafts');
        $showDrafts = $showDrafts === null ? ! app()->isProduction() : filter_var($showDrafts, FILTER_VALIDATE_BOOL);

        return array_filter($languages, static fn (array $language): bool => $showDrafts || ! $language['draft']);
    }

    public static function isAvailable(string $code): bool
    {
        return array_key_exists($code, self::available());
    }

    /**
     * Strings for the frontend: the requested language merged over English, so
     * untranslated keys still show readable English text.
     *
     * @return array<string, string>
     */
    public static function strings(string $locale): array
    {
        $english = self::load('en');

        return $locale === 'en' ? $english : array_merge($english, self::load($locale));
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        // Keys starting with "_" are notes for translators, not interface strings.
        return array_filter(
            $decoded,
            static fn (mixed $value, int|string $key): bool => is_string($value) && ! str_starts_with((string) $key, '_'),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
