import { usePage } from '@inertiajs/react';

export type Translations = Record<string, string>;

/**
 * Translate a key using the strings Laravel shares for the current language
 * (lang/<locale>.json). Missing keys fall back to English, then to the key.
 * Placeholders use Laravel's :name syntax.
 */
export function translate(strings: Translations, key: string, replace: Record<string, string | number> = {}): string {
    let text = strings[key] ?? key;
    for (const [name, value] of Object.entries(replace)) {
        text = text.replaceAll(`:${name}`, String(value));
    }
    return text;
}

export function useTranslation() {
    const { i18n } = usePage().props;
    return {
        t: (key: string, replace?: Record<string, string | number>) => translate(i18n.strings, key, replace),
        locale: i18n.locale,
        languages: i18n.languages,
    };
}
