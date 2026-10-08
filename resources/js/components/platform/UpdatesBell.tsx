import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

/** Bell with the unread updates count; opens the updates feed. Hidden for guests. */
export function UpdatesBell({ inverse }: { inverse?: boolean }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    if (!auth.user) return null;
    const unread = auth.user.unreadUpdates;
    const label =
        unread > 0
            ? `${t('common.notifications')} (${t('updates.unread', { count: unread })})`
            : t('common.notifications');

    return (
        <Link
            href="/home/updates"
            aria-label={label}
            className={cn(
                'rounded-control relative inline-grid size-11 place-items-center',
                inverse ? 'text-white hover:bg-white/10' : 'text-fg hover:bg-surface-muted',
            )}
        >
            <Bell className="size-5" aria-hidden />
            {unread > 0 && (
                <span
                    className="bg-kasi-marigold text-kasi-indigo absolute top-1.5 right-1.5 grid min-w-5 place-items-center rounded-full px-1 text-[0.65rem] font-bold"
                    aria-hidden
                >
                    {unread > 99 ? '99+' : unread}
                </span>
            )}
        </Link>
    );
}
