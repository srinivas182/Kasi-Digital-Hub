import { Head, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Download } from 'lucide-react';

import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { EventTypeBadge, eventWhen } from '../../components/EventBits';

interface Props {
    event: {
        id: string;
        type: string;
        title: string;
        room: string | null;
        hub: string;
        address: string | null;
        startsAt: string;
        endsAt: string;
        status: string;
        description: string | null;
        cancelReason: string | null;
        placesLeft: number;
        allowed: boolean;
        past: boolean;
    };
    myStatus: string | null;
    attended: boolean;
}

export default function EventPage({ event, myStatus, attended }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const cancelled = event.status === 'cancelled';
    const open = !cancelled && !event.past && event.allowed;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={event.title} />
            <div className="mx-auto max-w-2xl">
                {flash.status && <Alert tone="success" title={flash.status} />}
                {errors?.event && <Alert tone="danger" title={errors.event} />}
                <Card className="mt-4">
                    <div className="flex flex-wrap gap-2">
                        <EventTypeBadge type={event.type} />
                        {cancelled && <Badge tone="danger">{t('hubops.events.status.cancelled')}</Badge>}
                        {myStatus && (
                            <Badge tone={myStatus === 'registered' ? 'success' : 'warning'}>
                                {t(`hubops.events.status.${myStatus}`)}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-fg mt-3 text-2xl font-bold">{event.title}</h1>
                    <p className="text-fg mt-2">{eventWhen(event.startsAt, event.endsAt)}</p>
                    <p className="text-fg-muted">
                        {event.hub}
                        {event.room ? ` · ${event.room}` : ''}
                        {event.address ? ` · ${event.address}` : ''}
                    </p>
                    {cancelled && event.cancelReason && <p className="text-danger-text mt-3">{event.cancelReason}</p>}
                    {event.description && <p className="text-fg mt-4 whitespace-pre-line">{event.description}</p>}
                    {attended && (
                        <div className="mt-4">
                            <p className="text-success-text font-semibold">{t('hubops.visitor.attended')}</p>
                            <a
                                href={`/events/${event.id}/certificate`}
                                className={buttonVariants({ variant: 'secondary', className: 'mt-3' })}
                            >
                                <Download className="size-4" aria-hidden /> {t('hubops.visitor.certificate')}
                            </a>
                        </div>
                    )}
                    {!event.allowed && <p className="text-fg-muted mt-4">{t('hubops.events.adults_only')}</p>}

                    {open && !attended && (
                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            {myStatus === null ? (
                                <Button
                                    onClick={() =>
                                        router.post(`/events/${event.id}/register`, {}, { preserveScroll: true })
                                    }
                                >
                                    {event.placesLeft > 0
                                        ? t('hubops.visitor.register')
                                        : t('hubops.visitor.join_waitlist')}
                                </Button>
                            ) : (
                                <Button
                                    variant="secondary"
                                    onClick={() =>
                                        router.delete(`/events/${event.id}/register`, { preserveScroll: true })
                                    }
                                >
                                    {t('hubops.visitor.cancel')}
                                </Button>
                            )}
                            <span className="text-fg-muted text-sm">
                                {event.placesLeft > 0
                                    ? t('hubops.visitor.places_left', { count: event.placesLeft })
                                    : t('hubops.visitor.full')}
                            </span>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
