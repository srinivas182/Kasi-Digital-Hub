import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, UserRound } from 'lucide-react';

import { ThemeMenuItem } from '@/components/platform/chrome';
import { Avatar } from '@/components/ui/display';
import { Disclosure } from '@/components/ui/Disclosure';
import { useTranslation } from '@/lib/i18n';

/** Signed-in user's avatar with account and sign-out actions. Falls back to a plain avatar for previews. */
export function UserMenu({ fallbackName = 'Guest' }: { fallbackName?: string }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    if (!auth.user) return <Avatar name={fallbackName} size="sm" />;

    const item =
        'rounded-md text-fg hover:bg-surface-muted flex min-h-10 w-full items-center gap-2 px-3 text-left text-sm';

    return (
        <Disclosure
            label={t('account.title')}
            triggerClassName="rounded-full"
            panelClassName="w-56 p-1"
            trigger={<Avatar name={auth.user.name} size="sm" />}
        >
            {(close) => (
                <>
                    <p className="text-fg truncate px-3 pt-2 pb-1 text-sm font-semibold">{auth.user?.name}</p>
                    <Link href="/account" onClick={close} className={item}>
                        <UserRound className="size-4" aria-hidden />
                        {t('account.title')}
                    </Link>
                    <ThemeMenuItem className={`${item} sm:hidden`} />
                    <button type="button" className={item} onClick={() => router.post('/logout')}>
                        <LogOut className="size-4" aria-hidden />
                        {t('account.sign_out')}
                    </button>
                </>
            )}
        </Disclosure>
    );
}
