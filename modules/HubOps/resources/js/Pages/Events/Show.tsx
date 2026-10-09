import { Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Field } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { useTranslation } from '@/lib/i18n';

import { EventTypeBadge, eventWhen } from '../../components/EventBits';
import { HubOpsPage } from '../../components/HubOpsPage';
import { ReasonDialog } from '../../components/ReasonDialog';

interface Props {
    event: {
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
        description: string | null;
        cancelReason: string | null;
        attendanceOpen: boolean;
    };
    registrations: { id: string; name: string; phone: string; status: string; attended: boolean; byStaff: boolean }[];
    attendQr: string | null;
    canCancel: boolean;
}

export default function EventShow({ event, registrations, attendQr, canCancel }: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const add = useForm({ phone: '' });
    const cancelled = event.status === 'cancelled';
    const booked = registrations.filter((r) => r.status === 'registered');
    const waiting = registrations.filter((r) => r.status === 'waitlisted');

    return (
        <HubOpsPage
            title={event.title}
            crumbs={[{ label: t('hubops.events.title'), href: '/hub-ops/events' }, { label: event.title }]}
            actions={
                !cancelled && (
                    <>
                        <Link
                            href={`/hub-ops/events/${event.id}/edit`}
                            className={buttonVariants({ variant: 'secondary' })}
                        >
                            {t('hubops.events.edit')}
                        </Link>
                        {canCancel && (
                            <ReasonDialog
                                trigger={<Button variant="danger">{t('hubops.events.cancel')}</Button>}
                                title={t('hubops.events.cancel')}
                                description={t('hubops.events.cancel_body')}
                                confirmLabel={t('hubops.events.cancel')}
                                action={`/hub-ops/events/${event.id}/cancel`}
                                danger
                            />
                        )}
                    </>
                )
            }
        >
            {cancelled && (
                <Alert tone="danger" title={`${t('hubops.events.status.cancelled')}: ${event.cancelReason ?? ''}`} />
            )}
            {errors?.attendance && <Alert tone="danger" title={errors.attendance} />}
            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <div className="flex flex-wrap gap-2">
                            <EventTypeBadge type={event.type} />
                            <Badge>{t(`hubops.events.audience.${event.audience}`)}</Badge>
                        </div>
                        <p className="text-fg mt-2">
                            {eventWhen(event.startsAt, event.endsAt)}
                            {event.room ? ` · ${event.room}` : ''}
                        </p>
                        <p className="text-fg-muted mt-1 text-sm">
                            {t('hubops.events.places', { registered: event.registered, capacity: event.capacity })} ·{' '}
                            {t('hubops.events.waitlist')}: {event.waitlisted} · {t('hubops.events.attended_label')}:{' '}
                            {event.attended}
                        </p>
                        {event.description && <p className="text-fg mt-3 whitespace-pre-line">{event.description}</p>}
                    </Card>

                    <Card>
                        <CardTitle>
                            {t('hubops.events.signed_up')} ({booked.length})
                        </CardTitle>
                        <ul className="mt-3 flex flex-col gap-2" aria-label={t('hubops.events.signed_up')}>
                            {booked.map((r) => (
                                <li
                                    key={r.id}
                                    className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0"
                                >
                                    <span>
                                        <span className="text-fg font-semibold">{r.name}</span>{' '}
                                        <span className="text-fg-muted text-sm">{r.phone}</span>
                                    </span>
                                    <span className="flex gap-2">
                                        {r.attended ? (
                                            <Badge tone="success">{t('hubops.events.attended_label')}</Badge>
                                        ) : (
                                            !cancelled && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() =>
                                                            router.post(
                                                                `/hub-ops/events/${event.id}/registrations/${r.id}/attend`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {t('hubops.events.mark_attended')}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/hub-ops/events/${event.id}/registrations/${r.id}`,
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {t('hubops.settings.remove')}
                                                    </Button>
                                                </>
                                            )
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        {waiting.length > 0 && (
                            <>
                                <p className="text-fg mt-4 font-semibold">
                                    {t('hubops.events.waitlist')} ({waiting.length})
                                </p>
                                <ol className="mt-2 list-decimal pl-5 text-sm">
                                    {waiting.map((r) => (
                                        <li key={r.id} className="text-fg">
                                            {r.name} <span className="text-fg-muted">{r.phone}</span>
                                        </li>
                                    ))}
                                </ol>
                            </>
                        )}
                        {!cancelled && (
                            <form
                                className="border-line mt-4 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-end"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    add.post(`/hub-ops/events/${event.id}/registrations`, {
                                        preserveScroll: true,
                                        onSuccess: () => add.reset(),
                                    });
                                }}
                            >
                                <Field
                                    label={t('hubops.events.add_person')}
                                    error={add.errors.phone}
                                    className="flex-1"
                                >
                                    <PhoneInput
                                        value={add.data.phone}
                                        onChange={(_, raw) => add.setData('phone', raw)}
                                    />
                                </Field>
                                <Button type="submit" loading={add.processing}>
                                    {t('hubops.events.add')}
                                </Button>
                            </form>
                        )}
                    </Card>
                </div>

                {attendQr && (
                    <Card>
                        <CardTitle>{t('hubops.events.attend_qr')}</CardTitle>
                        <div
                            className="mt-3 rounded-lg bg-white p-3"
                            role="img"
                            aria-label={t('hubops.events.attend_qr')}
                            dangerouslySetInnerHTML={{ __html: attendQr }}
                        />
                        <p className="text-fg-muted mt-3 text-sm">{t('hubops.events.attend_qr_hint')}</p>
                    </Card>
                )}
            </div>
        </HubOpsPage>
    );
}
