import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

import { Button } from '@/components/ui/Button';
import { Badge, EmptyState } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { cn } from '@/lib/cn';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface UpdateItem {
    id: string;
    module: string;
    title: string;
    body: string | null;
    cause: string | null;
    url: string | null;
    read: boolean;
    createdAt: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    next_page_url: string | null;
    prev_page_url: string | null;
}

/** "What changed and why": every update from every service, newest first. */
export default function Updates({ updates }: { updates: Paginated<UpdateItem> }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const unread = updates.data.some((update) => !update.read);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('updates.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold tracking-tight">{t('updates.title')}</h1>
                    <p className="text-fg-muted text-sm">{t('updates.description')}</p>
                </div>
                {unread && (
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => router.post('/home/updates/read', {}, { preserveScroll: true })}
                    >
                        {t('updates.mark_all_read')}
                    </Button>
                )}
            </div>

            {updates.data.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<Bell className="size-8" aria-hidden />} title={t('updates.none')} />
                </div>
            ) : (
                <ul className="mt-6 flex flex-col gap-3">
                    {updates.data.map((update) => (
                        <li key={update.id}>
                            <Link
                                href={`/home/updates/${update.id}`}
                                className={cn(
                                    'rounded-card border-line bg-surface hover:bg-surface-muted block border p-4',
                                    !update.read && 'border-l-primary border-l-4',
                                )}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <p className={cn('text-fg', !update.read && 'font-semibold')}>
                                        {!update.read && <span className="sr-only">Unread: </span>}
                                        {update.title}
                                    </p>
                                    <Badge tone="neutral">{update.module}</Badge>
                                </div>
                                {update.body && <p className="text-fg-muted mt-1 text-sm">{update.body}</p>}
                                {update.cause && (
                                    <p className="text-fg-muted mt-2 text-xs italic">
                                        {t('updates.why', { cause: update.cause })}
                                    </p>
                                )}
                                <p className="text-fg-muted mt-2 text-xs">{formatDateTime(update.createdAt)}</p>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {(updates.prev_page_url || updates.next_page_url) && (
                <nav aria-label="Pagination" className="mt-6 flex justify-between">
                    {updates.prev_page_url ? (
                        <Link href={updates.prev_page_url} className="text-primary font-semibold">
                            ← Newer
                        </Link>
                    ) : (
                        <span />
                    )}
                    {updates.next_page_url && (
                        <Link href={updates.next_page_url} className="text-primary font-semibold">
                            Older →
                        </Link>
                    )}
                </nav>
            )}
        </AppLayout>
    );
}
