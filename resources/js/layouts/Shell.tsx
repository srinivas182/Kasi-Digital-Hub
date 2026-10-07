import { type ReactNode, useEffect } from 'react';

import { DemoBanner, OfflineIndicator, SkipLink } from '@/components/platform/chrome';
import { Toaster } from '@/components/ui/Toast';
import { useTranslation } from '@/lib/i18n';

/** Common frame for every layout: skip link, demo banner, offline notice and toasts. */
export function Shell({ children }: { children: ReactNode }) {
    const { locale } = useTranslation();

    // Keep <html lang> in step with the interface language after client-side visits,
    // so screen readers pronounce the page in the right language.
    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    return (
        <>
            <SkipLink />
            <DemoBanner />
            <OfflineIndicator />
            {children}
            <Toaster />
        </>
    );
}
