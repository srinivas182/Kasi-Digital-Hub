<?php

declare(strict_types=1);

namespace App\Support\Locale;

use Illuminate\Translation\FileLoader;

/**
 * Laravel does not fall back to English for missing keys in JSON translation files, so a
 * person using a partly translated language (isiZulu, Xitsonga drafts) would see raw keys
 * in messages from the server. This loader merges each language over English.
 */
final class FallbackJsonLoader extends FileLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);
        $fallback = (string) config('app.fallback_locale', 'en');

        if ($group !== '*' || $namespace !== '*' || $locale === $fallback) {
            return $lines;
        }

        return array_merge(parent::load($fallback, $group, $namespace), $lines);
    }
}
