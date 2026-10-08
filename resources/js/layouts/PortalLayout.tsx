import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { UpdatesBell } from '@/components/platform/UpdatesBell';
import { UserMenu } from '@/components/platform/UserMenu';
import { ThemeToggle } from '@/components/platform/chrome';
import { BottomNav, PortalSwitcher, Sidebar } from '@/components/platform/nav';

import { Shell } from './Shell';

/** A service portal (KasiWork, KasiLearn...): sidebar on desktop, bottom nav on phones. */
export function PortalLayout({
    module,
    children,
    userName = 'Guest',
}: {
    module: string;
    children: ReactNode;
    userName?: string;
}) {
    const { navigation } = usePage().props;
    const portal = navigation.portals.find((item) => item.module === module);
    const items = portal?.items ?? [];

    return (
        <Shell>
            <header className="border-line bg-surface sticky top-0 z-30 border-b">
                <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5 sm:px-6">
                    <BrandMark />
                    {portal && (
                        <span className="border-line text-fg-muted hidden border-l pl-3 text-sm font-semibold sm:inline">
                            {portal.title}
                        </span>
                    )}
                    <div className="ml-auto flex items-center gap-1">
                        <PortalSwitcher />
                        <ThemeToggle />
                        <UpdatesBell />
                        <UserMenu fallbackName={userName} />
                    </div>
                </div>
            </header>
            <div className="mx-auto flex max-w-7xl gap-8 px-4 sm:px-6">
                <aside className="sticky top-16 hidden h-fit w-56 shrink-0 py-6 md:block">
                    <Sidebar items={items} label={portal?.title ?? module} />
                </aside>
                <main id="main" className="min-w-0 flex-1 pt-6 pb-24 md:pb-10">
                    {children}
                </main>
            </div>
            <BottomNav items={items} label={portal?.title ?? module} />
        </Shell>
    );
}
