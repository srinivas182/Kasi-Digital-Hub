import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { LanguageSwitcher, ThemeToggle } from '@/components/platform/chrome';
import { buttonVariants } from '@/components/ui/Button';
import { useTranslation } from '@/lib/i18n';

import { Shell } from './Shell';

export interface PublicLink {
    label: string;
    href: string;
}

/** Public website: header, footer, language switch. */
export function PublicLayout({ children, links = [] }: { children: ReactNode; links?: PublicLink[] }) {
    const { platform } = usePage().props;
    const { t } = useTranslation();
    return (
        <Shell>
            <header className="border-line bg-surface border-b">
                <div className="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6">
                    <BrandMark />
                    <nav aria-label="Main" className="text-fg-muted ml-6 hidden gap-6 text-sm font-medium lg:flex">
                        {links.map((link) => (
                            <Link key={link.href} href={link.href} className="hover:text-fg">
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="ml-auto flex items-center gap-1 sm:gap-2">
                        <LanguageSwitcher className="hidden sm:flex" />
                        <ThemeToggle />
                        <Link href="/login" className={buttonVariants({ variant: 'accent', size: 'sm' })}>
                            {t('common.sign_in')}
                        </Link>
                    </div>
                </div>
            </header>
            <main id="main" className="min-h-[60vh]">
                {children}
            </main>
            <footer className="border-line bg-surface border-t">
                <div className="text-fg-muted mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>{t('common.footer_owner', { owner: platform.owner })}</p>
                    <nav aria-label="Footer" className="flex flex-wrap gap-4">
                        <Link href="/privacy" className="hover:text-fg">
                            {t('common.privacy')}
                        </Link>
                        <Link href="/terms" className="hover:text-fg">
                            {t('common.terms')}
                        </Link>
                        <Link href="/help" className="hover:text-fg">
                            {t('common.help')}
                        </Link>
                        <Link href="/contact" className="hover:text-fg">
                            {t('common.contact')}
                        </Link>
                    </nav>
                    <LanguageSwitcher className="sm:hidden" />
                </div>
            </footer>
        </Shell>
    );
}
