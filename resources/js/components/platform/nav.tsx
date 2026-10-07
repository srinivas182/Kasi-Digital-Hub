import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';

import { Disclosure } from '@/components/ui/Disclosure';
import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';
import { navIcon } from '@/lib/icons';
import type { NavItem } from '@/types/global';

function isActive(href: string, current: string): boolean {
    return current === href || (href !== '/' && current.startsWith(`${href}/`));
}

/** Grid of every portal the user can open. Filtered by roles and hub package from Sprint 3. */
export function PortalSwitcher({ inverse }: { inverse?: boolean }) {
    const { navigation } = usePage().props;
    const { t } = useTranslation();
    return (
        <Disclosure
            label={t('common.portals')}
            panelClassName="w-72"
            triggerClassName={cn(
                'rounded-control inline-flex min-h-11 items-center gap-2 px-3 text-sm font-semibold',
                inverse ? 'text-white hover:bg-white/10' : 'text-fg hover:bg-surface-muted',
            )}
            trigger={
                <>
                    <LayoutGrid className="size-5" aria-hidden />
                    <span className="hidden sm:inline" aria-hidden>
                        {t('common.portals')}
                    </span>
                </>
            }
        >
            {(close) => (
                <>
                    <p className="text-fg-muted mb-3 text-xs font-semibold uppercase">{t('common.portals')}</p>
                    <ul className="grid grid-cols-2 gap-2">
                        {navigation.portals.map((portal) => {
                            const Icon = navIcon(portal.icon);
                            return (
                                <li key={portal.module}>
                                    <Link
                                        href={portal.href ?? '/'}
                                        onClick={close}
                                        className="rounded-control border-line text-fg hover:bg-surface-muted flex min-h-16 flex-col items-start gap-1 border p-2.5 text-xs font-semibold"
                                    >
                                        <Icon className="text-primary size-4" aria-hidden />
                                        {portal.title}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </>
            )}
        </Disclosure>
    );
}

/** Phone navigation: up to five destinations along the bottom of the screen. */
export function BottomNav({ items, label }: { items: NavItem[]; label: string }) {
    const { url } = usePage();
    const { t } = useTranslation();
    return (
        <nav
            aria-label={label}
            className="border-line bg-surface fixed inset-x-0 bottom-0 z-40 border-t pb-[env(safe-area-inset-bottom)] md:hidden"
        >
            <ul className="flex">
                {items.slice(0, 5).map((item) => {
                    const Icon = navIcon(item.icon);
                    const active = isActive(item.href, url);
                    return (
                        <li key={item.href} className="flex-1">
                            <Link
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-16 flex-col items-center justify-center gap-1 text-[0.7rem] font-medium',
                                    active ? 'text-primary' : 'text-fg-muted',
                                )}
                            >
                                <Icon className="size-5" aria-hidden />
                                {t(item.label)}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

/** Desktop side navigation. `inverse` gives the dark console style. */
export function Sidebar({ items, label, inverse }: { items: NavItem[]; label: string; inverse?: boolean }) {
    const { url } = usePage();
    const { t } = useTranslation();
    return (
        <nav aria-label={label}>
            <ul className="flex flex-col gap-1">
                {items.map((item) => {
                    const Icon = navIcon(item.icon);
                    const active = isActive(item.href, url);
                    return (
                        <li key={item.href}>
                            <Link
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'rounded-control flex min-h-11 items-center gap-3 px-3 text-sm font-medium',
                                    inverse
                                        ? active
                                            ? 'bg-white/15 text-white'
                                            : 'text-white/75 hover:bg-white/10 hover:text-white'
                                        : active
                                          ? 'bg-primary-soft text-primary'
                                          : 'text-fg-muted hover:bg-surface-muted hover:text-fg',
                                )}
                            >
                                <Icon className="size-5" aria-hidden />
                                {t(item.label)}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
