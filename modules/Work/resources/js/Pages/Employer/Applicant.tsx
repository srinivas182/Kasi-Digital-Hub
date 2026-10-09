import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { MatchScore } from '../../components/MatchScore';
import { type Message, Thread, Timeline } from '../../components/Thread';

interface Props {
    listing: { id: string; title: string };
    application: {
        id: string;
        stage: string;
        score: number | null;
        reference: string;
        blind: boolean;
        name: string;
        phone: string | null;
        headline: string | null;
        answers: { question: string; answer: string }[];
        message: string | null;
        cvUrl: string | null;
        reasons: string[];
        hireConfirmed: boolean;
    };
    stages: string[];
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
    notes: { body: string; author: string | null; at: string }[];
}

export default function Applicant({ listing, application, stages, timeline, interviews, messages, notes }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const note = useForm({ body: '' });
    const interview = useForm({ starts_at: '', mode: 'in_person', place: '', note: '' });
    const current = interviews.find((i) => i.status !== 'cancelled');
    const base = `/work/employer/listings/${listing.id}/applicants`;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={application.name} />
            <Link href={base} className="text-primary text-sm font-semibold hover:underline">
                ← {t('work.pipeline.title')}: {listing.title}
            </Link>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{application.name}</h1>
                    <p className="text-fg-muted text-sm">
                        {[application.headline, application.phone].filter(Boolean).join(' · ')}
                    </p>
                    {application.blind && <p className="text-fg-muted text-xs">{t('work.pipeline.blind')}</p>}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {application.score !== null && <MatchScore score={application.score} />}
                    <Select
                        aria-label={t('work.pipeline.move_to')}
                        value={application.stage}
                        onChange={(e) =>
                            router.post(
                                `${base}/move`,
                                { ids: [application.id], stage: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {[...stages, ...(application.stage === 'withdrawn' ? ['withdrawn'] : [])].map((s) => (
                            <option key={s} value={s}>
                                {t(`work.stage.${s}`)}
                            </option>
                        ))}
                    </Select>
                    {application.cvUrl && (
                        <a
                            href={application.cvUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary text-sm font-semibold hover:underline"
                        >
                            {t('work.pipeline.cv')}
                        </a>
                    )}
                </div>
            </div>
            {application.hireConfirmed && <Badge tone="success">{t('work.pipeline.hire_confirmed')}</Badge>}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('work.matches.why')}</CardTitle>
                        <ul className="text-fg mt-2 list-disc pl-5 text-sm">
                            {application.reasons.map((r) => (
                                <li key={r}>{r}</li>
                            ))}
                        </ul>
                        {application.answers.length > 0 && (
                            <>
                                <p className="text-fg mt-4 text-sm font-semibold">{t('work.pipeline.answers')}</p>
                                <dl className="mt-1 text-sm">
                                    {application.answers.map((a) => (
                                        <div key={a.question} className="mt-1">
                                            <dt className="text-fg-muted">{a.question}</dt>
                                            <dd className="text-fg font-medium">{a.answer || '-'}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </>
                        )}
                        {application.message && <p className="text-fg mt-4 text-sm">“{application.message}”</p>}
                    </Card>
                    <Card>
                        <CardTitle>{t('work.interview.title')}</CardTitle>
                        {current && (
                            <p className="text-fg mt-2 text-sm">
                                {formatDateTime(current.startsAt)} · {t(`work.interview.mode.${current.mode}`)}
                                {current.place ? ` · ${current.place}` : ''}{' '}
                                <Badge tone={current.status === 'confirmed' ? 'success' : 'warning'}>
                                    {t(`work.interview.status.${current.status}`)}
                                </Badge>
                            </p>
                        )}
                        <form
                            className="mt-3 grid gap-3 sm:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                interview.post(`${base}/${application.id}/interviews`, {
                                    preserveScroll: true,
                                    onSuccess: () => interview.reset(),
                                });
                            }}
                        >
                            <Field label={t('work.interview.when')} error={interview.errors.starts_at}>
                                <Input
                                    type="datetime-local"
                                    value={interview.data.starts_at}
                                    onChange={(e) => interview.setData('starts_at', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.interview.mode')}>
                                <Select
                                    value={interview.data.mode}
                                    onChange={(e) => interview.setData('mode', e.target.value)}
                                >
                                    {['in_person', 'hub', 'phone', 'video'].map((m) => (
                                        <option key={m} value={m}>
                                            {t(`work.interview.mode.${m}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field
                                label={t('work.interview.place')}
                                error={interview.errors.place}
                                className="sm:col-span-2"
                            >
                                <Input
                                    value={interview.data.place}
                                    onChange={(e) => interview.setData('place', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.interview.note')} className="sm:col-span-2">
                                <Input
                                    value={interview.data.note}
                                    onChange={(e) => interview.setData('note', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" size="sm" loading={interview.processing}>
                                    {t('work.interview.propose')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                    <Card>
                        <CardTitle>{t('work.timeline.title')}</CardTitle>
                        <div className="mt-3">
                            <Timeline events={timeline} />
                        </div>
                    </Card>
                </div>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('work.messages.title')}</CardTitle>
                        <div className="mt-3">
                            <Thread messages={messages} action={`${base}/${application.id}/messages`} />
                        </div>
                    </Card>
                    <Card>
                        <CardTitle>{t('work.pipeline.notes')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-2 text-sm">
                            {notes.map((n, i) => (
                                <li key={i}>
                                    <p className="text-fg whitespace-pre-line">{n.body}</p>
                                    <p className="text-fg-muted text-xs">
                                        {n.author} · {formatDateTime(n.at)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        <form
                            className="mt-3 flex flex-col gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                note.post(`${base}/${application.id}/notes`, {
                                    preserveScroll: true,
                                    onSuccess: () => note.reset(),
                                });
                            }}
                        >
                            <Field label={t('work.pipeline.add_note')}>
                                <Textarea
                                    rows={2}
                                    value={note.data.body}
                                    onChange={(e) => note.setData('body', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" size="sm" variant="secondary" disabled={!note.data.body.trim()}>
                                    {t('work.pipeline.add_note')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
