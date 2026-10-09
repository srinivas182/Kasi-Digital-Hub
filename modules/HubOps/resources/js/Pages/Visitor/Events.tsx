import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { EventTypeBadge, eventWhen } from '../../components/EventBits';

interface EventCard {
    id: string;
    type: string;
    title: string;
    room: string | null;
    hub: string;
    startsAt: string;
    endsAt: string;
    myStatus: string | null;
}

export default function Events({
    hubId,
    hubs,
    events,
}: {
    hubId: string | null;
    hubs: { id: string; name: string }[];
    events: EventCard[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('hubops.visitor.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('hubops.visitor.title')}</h1>
                <Select
                    aria-label={t('hubops.hub')}
                    className="max-w-64"
                    value={hubId ?? ''}
                    onChange={(e) => router.get('/events', { hub: e.target.value || undefined })}
                >
                    <option value="">{t('hubops.visitor.all_hubs')}</option>
                    {hubs.map((h) => (
                        <option key={h.id} value={h.id}>
                            {h.name}
                        </option>
                    ))}
                </Select>
            </div>
            {events.length === 0 ? (
                <div className="mt-6">
                    <EmptyState
                        icon={<CalendarDays className="size-8" aria-hidden />}
                        title={t('hubops.events.none')}
                    />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2">
                    {events.map((e) => (
                        <li key={e.id}>
                            <Link href={`/events/${e.id}`} className="block h-full">
                                <Card className="hover:bg-surface-muted h-full">
                                    <div className="flex flex-wrap gap-2">
                                        <EventTypeBadge type={e.type} />
                                        {e.myStatus && (
                                            <Badge tone={e.myStatus === 'registered' ? 'success' : 'warning'}>
                                                {t(`hubops.events.status.${e.myStatus}`)}
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-fg mt-2 text-lg font-semibold">{e.title}</p>
                                    <p className="text-fg-muted text-sm">{eventWhen(e.startsAt, e.endsAt)}</p>
                                    <p className="text-fg-muted text-sm">{e.hub}</p>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
