import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { type Message, Thread, Timeline } from '../../components/Thread';

interface Props {
    person: { name: string; assisted: boolean };
    application: {
        id: string;
        stage: string;
        title: string;
        listingId: string;
        employer: string;
        appliedAt: string;
        answers: { question: string; answer: string }[];
        message: string | null;
        hired: boolean;
        hireConfirmed: boolean;
        hiredOn: string | null;
    };
    timeline: { kind: string; stage: string | null; at: string; meta: Record<string, unknown> }[];
    interviews: {
        id: string;
        startsAt: string;
        mode: string;
        place: string | null;
        note: string | null;
        status: string;
    }[];
    messages: Message[];
    retention: { id: number; days: number }[];
}

export default function ApplicationShow({ person, application, timeline, interviews, messages, retention }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [startedOn, setStartedOn] = useState(application.hiredOn ?? '');
    const open = ['new', 'shortlisted', 'interview', 'offer'].includes(application.stage);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={application.title} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <Link href="/work/applications" className="text-primary text-sm font-semibold hover:underline">
                ← {t('work.applications.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{application.title}</h1>
                    <p className="text-fg-muted">
                        {application.employer} ·{' '}
                        {t('work.applications.applied', { date: formatDate(application.appliedAt) })}
                    </p>
                </div>
                <Badge tone={application.stage === 'unsuccessful' ? 'danger' : 'primary'}>
                    {t(`work.stage.${application.stage}`)}
                </Badge>
            </div>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.application && (
                <div className="mt-4">
                    <Alert tone="danger" title={errors.application} />
                </div>
            )}
            {application.hired && !application.hireConfirmed && (
                <Card className="border-primary mt-4">
                    <CardTitle>{t('work.hire.question', { employer: application.employer })}</CardTitle>
                    <Field label={t('work.hire.started_on')} className="mt-3 max-w-56">
                        <Input type="date" value={startedOn} onChange={(e) => setStartedOn(e.target.value)} />
                    </Field>
                    <div className="mt-3 flex gap-2">
                        <Button
                            onClick={() =>
                                router.post(
                                    `/work/applications/${application.id}/hire`,
                                    { started: true, started_on: startedOn || null },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('work.hire.yes')}
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                router.post(
                                    `/work/applications/${application.id}/hire`,
                                    { started: false },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('work.hire.no')}
                        </Button>
                    </div>
                </Card>
            )}
            {retention.map((r) => (
                <Card key={r.id} className="border-primary mt-4">
                    <CardTitle>{t('work.hire.retention_question', { days: r.days })}</CardTitle>
                    <div className="mt-3 flex gap-2">
                        <Button
                            onClick={() =>
                                router.post(
                                    `/work/retention/${r.id}`,
                                    { still_working: true },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('work.hire.still')}
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                router.post(
                                    `/work/retention/${r.id}`,
                                    { still_working: false },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('work.hire.left')}
                        </Button>
                    </div>
                </Card>
            ))}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="flex flex-col gap-6">
                    {interviews.length > 0 && (
                        <Card>
                            <CardTitle>{t('work.interview.title')}</CardTitle>
                            {interviews
                                .filter((i) => i.status !== 'cancelled')
                                .slice(0, 1)
                                .map((i) => (
                                    <div key={i.id} className="mt-2">
                                        <p className="text-fg font-semibold">{formatDateTime(i.startsAt)}</p>
                                        <p className="text-fg text-sm">
                                            {t(`work.interview.mode.${i.mode}`)}
                                            {i.place ? ` · ${i.place}` : ''}
                                        </p>
                                        {i.note && <p className="text-fg-muted text-sm">{i.note}</p>}
                                        <Badge className="mt-2" tone={i.status === 'confirmed' ? 'success' : 'warning'}>
                                            {t(`work.interview.status.${i.status}`)}
                                        </Badge>
                                        {i.status === 'proposed' && (
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            `/work/interviews/${i.id}`,
                                                            { answer: 'confirmed' },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {t('work.interview.confirm')}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        router.post(
                                                            `/work/interviews/${i.id}`,
                                                            { answer: 'reschedule_requested' },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {t('work.interview.reschedule')}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.post(
                                                            `/work/interviews/${i.id}`,
                                                            { answer: 'declined' },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {t('work.interview.decline')}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                        </Card>
                    )}
                    <Card>
                        <CardTitle>{t('work.timeline.title')}</CardTitle>
                        <div className="mt-3">
                            <Timeline events={timeline} />
                        </div>
                        {open && (
                            <Button
                                size="sm"
                                variant="ghost"
                                className="mt-4"
                                onClick={() =>
                                    router.post(
                                        `/work/applications/${application.id}/withdraw`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('work.apply.withdraw')}
                            </Button>
                        )}
                    </Card>
                </div>
                <Card>
                    <CardTitle>{t('work.messages.title')}</CardTitle>
                    <div className="mt-3">
                        <Thread messages={messages} action={`/work/applications/${application.id}/messages`} />
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
