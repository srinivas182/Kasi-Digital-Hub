import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { UpdatesBell } from '@/components/platform/UpdatesBell';
import { UserMenu } from '@/components/platform/UserMenu';
import { ThemeToggle } from '@/components/platform/chrome';
import { PortalSwitcher, Sidebar } from '@/components/platform/nav';
import { type Crumb, Breadcrumbs } from '@/components/ui/navigation';

import { Shell } from './Shell';

/** Admin, commercial, funder and regional consoles: dark sidebar, dense content, desktop-first. */
export function ConsoleLayout({
    module,
    breadcrumbs = [],
    actions,
    children,
    userName = 'Admin',
}: {
    module: string;
    breadcrumbs?: Crumb[];
    actions?: ReactNode;
    children: ReactNode;
    userName?: string;
}) {
    const { navigation } = usePage().props;
    const portal = navigation.portals.find((item) => item.module === module);

    return (
        <Shell>
            <div className="flex min-h-screen">
                <aside className="bg-kasi-indigo hidden w-64 shrink-0 flex-col gap-6 p-4 lg:flex">
                    <BrandMark inverse />
                    {portal && (
                        <p className="text-kasi-marigold text-xs font-semibold tracking-wide uppercase">
                            {portal.title}
                        </p>
                    )}
                    <Sidebar items={portal?.items ?? []} label={portal?.title ?? module} inverse />
                </aside>
                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="border-line bg-surface sticky top-0 z-30 flex items-center gap-3 border-b px-4 py-2.5 sm:px-6">
                        <div className="lg:hidden">
                            <BrandMark />
                        </div>
                        <div className="hidden lg:block">
                            {breadcrumbs.length > 0 && <Breadcrumbs items={breadcrumbs} />}
                        </div>
                        <div className="ml-auto flex items-center gap-1">
                            {actions}
                            <PortalSwitcher />
                            <ThemeToggle className="hidden sm:inline-grid" />
                            <UpdatesBell />
                            <UserMenu fallbackName={userName} />
                        </div>
                    </header>
                    <main id="main" className="w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
                        {children}
                    </main>
                </div>
            </div>
        </Shell>
    );
}
