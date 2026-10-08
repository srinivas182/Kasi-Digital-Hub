import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { UpdatesBell } from '@/components/platform/UpdatesBell';
import { UserMenu } from '@/components/platform/UserMenu';
import { LanguageSwitcher, ThemeToggle } from '@/components/platform/chrome';
import { BottomNav, PortalSwitcher } from '@/components/platform/nav';

import { Shell } from './Shell';

/** Hub home: top bar on desktop, bottom tab bar on phones. */
export function AppLayout({ children, userName = 'Guest' }: { children: ReactNode; userName?: string }) {
    const { navigation } = usePage().props;
    const hub = navigation.portals.find((portal) => portal.module === 'Hub');

    return (
        <Shell>
            <header className="bg-kasi-indigo sticky top-0 z-30 text-white">
                <div className="mx-auto flex max-w-6xl items-center gap-2 px-4 py-2.5 sm:px-6">
                    <BrandMark inverse />
                    <div className="ml-auto flex items-center gap-1">
                        <PortalSwitcher inverse />
                        <LanguageSwitcher inverse className="hidden md:flex" />
                        <ThemeToggle inverse />
                        <UpdatesBell inverse />
                        <UserMenu fallbackName={userName} />
                    </div>
                </div>
            </header>
            <main id="main" className="mx-auto max-w-6xl px-4 pt-6 pb-24 sm:px-6 md:pb-10">
                {children}
            </main>
            {hub && <BottomNav items={hub.items} label={hub.title} />}
        </Shell>
    );
}
