import { Link, router } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { Badge, Card, EmptyState, ProgressBar } from '@/components/ui/display';
import { useTranslation } from '@/lib/i18n';

import { EventTypeBadge, eventWhen } from '../../components/EventBits';
import { type HubChoice, HubOpsPage, type Paginated } from '../../components/HubOpsPage';

interface EventRow {
    id: string;
    type: string;
    title: string;
    room: string | null;
    startsAt: string;
    endsAt: string;
    capacity: number;
    audience: string;
    status: string;
    registered: number;
    waitlisted: number;
    attended: number;
}

export default function EventsIndex({
    hubs,
    when,
    events,
}: {
    hubs: HubChoice;
    when: string;
    events: Paginated<EventRow>;
}) {
    const { t } = useTranslation();

    return (
        <HubOpsPage
            title={t('hubops.events.title')}
            hubs={hubs}
            crumbs={[{ label: t('hubops.events.title') }]}
            actions={
                <Link href="/hub-ops/events/create" className={buttonVariants({})}>
                    <Plus className="size-4" aria-hidden /> {t('hubops.events.new')}
                </Link>
            }
        >
            <div className="mb-4 flex gap-2" role="group" aria-label={t('hubops.events.title')}>
                {(['upcoming', 'past'] as const).map((w) => (
                    <button
                        key={w}
                        type="button"
                        aria-pressed={when === w}
                        onClick={() => router.get('/hub-ops/events', { when: w === 'past' ? 'past' : undefined })}
                        className={
                            when === w
                                ? 'bg-primary text-primary-fg rounded-full px-4 py-2 text-sm font-semibold'
                                : 'border-line text-fg hover:bg-surface-muted rounded-full border px-4 py-2 text-sm font-semibold'
                        }
                    >
                        {t(`hubops.events.${w}`)}
                    </button>
                ))}
            </div>
            {events.data.length === 0 ? (
                <EmptyState icon={<CalendarDays className="size-8" aria-hidden />} title={t('hubops.events.none')} />
            ) : (
                <ul className="grid gap-4 md:grid-cols-2" aria-label={t('hubops.events.title')}>
                    {events.data.map((e) => (
                        <li key={e.id}>
                            <Link href={`/hub-ops/events/${e.id}`} className="block h-full">
                                <Card className="hover:bg-surface-muted h-full">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <EventTypeBadge type={e.type} />
                                        {e.status === 'cancelled' && (
                                            <Badge tone="danger">{t('hubops.events.status.cancelled')}</Badge>
                                        )}
                                    </div>
                                    <p className="text-fg mt-2 text-lg font-semibold">{e.title}</p>
                                    <p className="text-fg-muted text-sm">
                                        {eventWhen(e.startsAt, e.endsAt)}
                                        {e.room ? ` · ${e.room}` : ''}
                                    </p>
                                    <div className="mt-3">
                                        <ProgressBar
                                            value={Math.round((100 * e.registered) / e.capacity)}
                                            label={t('hubops.events.places', {
                                                registered: e.registered,
                                                capacity: e.capacity,
                                            })}
                                        />
                                    </div>
                                    <p className="text-fg-muted mt-2 text-sm">
                                        {e.waitlisted > 0 && `${t('hubops.events.waitlist')}: ${e.waitlisted} · `}
                                        {when === 'past' && `${t('hubops.events.attended_label')}: ${e.attended}`}
                                    </p>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </HubOpsPage>
    );
}
