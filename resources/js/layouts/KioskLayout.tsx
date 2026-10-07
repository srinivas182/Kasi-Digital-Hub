import type { ReactNode } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { LanguageSwitcher } from '@/components/platform/chrome';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { useIdleTimer } from '@/hooks/useIdleTimer';
import { useTranslation } from '@/lib/i18n';

import { Shell } from './Shell';

export interface KioskLayoutProps {
    hubName: string;
    children: ReactNode;
    /** Called when the user is idle too long - signs them out on shared hub computers. */
    onTimeout: () => void;
    timeoutSeconds?: number;
    warningSeconds?: number;
}

/** Shared hub computers: large targets, minimal navigation, automatic sign-out when idle. */
export function KioskLayout({
    hubName,
    children,
    onTimeout,
    timeoutSeconds = 180,
    warningSeconds = 30,
}: KioskLayoutProps) {
    const { t } = useTranslation();
    const { remaining, stayActive } = useIdleTimer({ timeoutSeconds, warningSeconds, onTimeout });

    return (
        <Shell>
            <header className="bg-kasi-indigo text-white">
                <div className="mx-auto flex max-w-4xl items-center gap-4 px-6 py-4">
                    <BrandMark inverse />
                    <span className="hidden text-base text-white/80 sm:inline">{hubName}</span>
                    <LanguageSwitcher inverse className="ml-auto" />
                </div>
            </header>
            <main id="main" className="mx-auto max-w-4xl px-6 py-8 text-lg">
                {children}
            </main>
            <Dialog
                open={remaining !== null}
                onOpenChange={(open) => {
                    if (!open) stayActive();
                }}
                title={t('kiosk.timeout_title')}
                description={t('kiosk.timeout_body', { seconds: remaining ?? 0 })}
                footer={
                    <>
                        <Button variant="secondary" size="lg" onClick={onTimeout}>
                            {t('kiosk.sign_out')}
                        </Button>
                        <Button size="lg" onClick={stayActive}>
                            {t('kiosk.stay')}
                        </Button>
                    </>
                }
            />
        </Shell>
    );
}
