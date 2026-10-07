import { router, usePage } from '@inertiajs/react';
import { Laptop, Moon, Sun, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';

import { IconButton } from '@/components/ui/Button';
import { type ThemeChoice, useTheme } from '@/hooks/useTheme';
import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

/** First focusable element on every page: jumps keyboard users past the navigation. */
export function SkipLink() {
    const { t } = useTranslation();
    return (
        <a
            href="#main"
            className="rounded-control bg-kasi-marigold text-kasi-indigo sr-only z-[60] px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
        >
            {t('common.skip_to_content')}
        </a>
    );
}

/** Shown on every screen in demo environments so screenshots are never mistaken for real data. */
export function DemoBanner() {
    const { platform } = usePage().props;
    const { t } = useTranslation();
    if (!platform.demo) return null;
    return (
        <div role="note" className="bg-kasi-marigold text-kasi-indigo px-4 py-1.5 text-center text-xs font-semibold">
            {t('common.demo_banner')}
        </div>
    );
}

export function OfflineIndicator() {
    const { t } = useTranslation();
    const [offline, setOffline] = useState(() => typeof navigator !== 'undefined' && !navigator.onLine);

    useEffect(() => {
        const update = () => setOffline(!navigator.onLine);
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    if (!offline) return null;
    return (
        <div
            role="status"
            className="bg-kasi-indigo flex items-center justify-center gap-2 px-4 py-2 text-sm text-white"
        >
            <WifiOff className="size-4" aria-hidden />
            {t('common.offline')}
        </div>
    );
}

export function LanguageSwitcher({ className, inverse }: { className?: string; inverse?: boolean }) {
    const { t, locale, languages } = useTranslation();
    if (languages.length < 2) return null;
    return (
        <label className={cn('flex items-center gap-2 text-sm', className)}>
            <span className="sr-only">{t('common.language')}</span>
            <select
                value={locale}
                onChange={(event) => router.post('/locale', { locale: event.target.value }, { preserveScroll: true })}
                className={cn(
                    'rounded-control min-h-9 border px-2 text-sm',
                    inverse ? 'border-white/30 bg-white/10 text-white' : 'border-line bg-surface text-fg',
                )}
            >
                {languages.map((language) => (
                    <option key={language.code} value={language.code} className="text-fg">
                        {language.name}
                        {language.draft ? ` (${t('common.language_draft')})` : ''}
                    </option>
                ))}
            </select>
        </label>
    );
}

const NEXT_THEME: Record<ThemeChoice, ThemeChoice> = { system: 'light', light: 'dark', dark: 'system' };

/**
 * Cycles light -> dark -> phone setting. A plain button (no menu library) keeps the
 * public pages light on low-end phones.
 */
export function ThemeToggle({ inverse }: { inverse?: boolean }) {
    const { t } = useTranslation();
    const { choice, setTheme } = useTheme();
    const Icon = choice === 'dark' ? Moon : choice === 'light' ? Sun : Laptop;
    const current = t(
        choice === 'dark' ? 'common.theme_dark' : choice === 'light' ? 'common.theme_light' : 'common.theme_system',
    );
    return (
        <IconButton
            label={`${t('common.theme')}: ${current}`}
            className={inverse ? 'text-white hover:bg-white/10' : undefined}
            onClick={() => setTheme(NEXT_THEME[choice])}
        >
            <Icon className="size-5" aria-hidden />
        </IconButton>
    );
}
