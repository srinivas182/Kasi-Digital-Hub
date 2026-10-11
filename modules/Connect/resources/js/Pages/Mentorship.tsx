import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { ReportConcern } from '../components/ReportConcern';

interface Session {
    id: number;
    startsAt: string;
    minutes: number;
    format: string;
    link: string | null;
    status: string;
    sharedNotes: string | null;
    actions: string[];
    privateNotes: string | null;
    helpful: boolean | null;
}

interface Props {
    mentorship: {
        id: string;
        status: string;
        goal: string;
        role: string;
        with: string;
        goals: string[];
        perMonth: number;
        months: number;
        format: string;
        mentorAgreed: boolean;
        menteeAgreed: boolean;
        startsOn: string | null;
        endsOn: string | null;
        renewed: boolean;
        myFeedback: string | null;
    };
    isMentor: boolean;
    menteeMinor: boolean;
    otherId: string;
    sessions: Session[];
    messages: { id: string; body: string; mine: boolean; held: boolean; at: string }[];
    hubs: { id: string; name: string }[];
}

function RecordSession({ session }: { session: Session }) {
    const { t } = useTranslation();
    const form = useForm({ shared_notes: '', actions: [''] as string[], private_notes: '' });
    return (
        <form
            className="mt-2 flex flex-col gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/connect/sessions/${session.id}/record`, { preserveScroll: true });
            }}
        >
            <Field label={t('connect.session.shared_notes')}>
                <Textarea
                    rows={3}
                    value={form.data.shared_notes}
                    onChange={(e) => form.setData('shared_notes', e.target.value)}
                />
            </Field>
            <Field label={t('connect.session.action')}>
                <Input value={form.data.actions[0] ?? ''} onChange={(e) => form.setData('actions', [e.target.value])} />
            </Field>
            <Field label={t('connect.session.private_notes')} hint={t('connect.session.private_hint')}>
                <Textarea
                    rows={2}
                    value={form.data.private_notes}
                    onChange={(e) => form.setData('private_notes', e.target.value)}
                />
            </Field>
            <div>
                <Button type="submit" size="sm" disabled={!form.data.shared_notes.trim()}>
                    {t('connect.session.record')}
                </Button>
            </div>
        </form>
    );
}

export default function Mentorship({ mentorship, isMentor, menteeMinor, otherId, sessions, messages, hubs }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const base = `/connect/mentorships/${mentorship.id}`;
    const agreement = useForm({
        goals: mentorship.goals.length ? mentorship.goals : [''],
        per_month: String(mentorship.perMonth),
        months: String(mentorship.months),
        format: mentorship.format,
    });
    const book = useForm({
        starts_at: '',
        minutes: '60',
        format: menteeMinor ? 'hub' : 'phone',
        link: '',
        hub_id: hubs[0]?.id ?? '',
        room: '',
    });
    const [message, setMessage] = useState('');
    const [ending, setEnding] = useState(false);
    const end = useForm({ reason: '', goals_met: mentorship.goals.map(() => false), feedback: '' });
    const iAgreed = isMentor ? mentorship.mentorAgreed : mentorship.menteeAgreed;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('connect.title')} />
            <Link href="/connect" className="text-primary text-sm font-semibold hover:underline">
                ← {t('connect.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">
                        {t(isMentor ? 'connect.you_mentor' : 'connect.your_mentor', { name: mentorship.with })}
                    </h1>
                    <p className="text-fg-muted">
                        {t(`connect.goal.${mentorship.goal}`)}
                        {mentorship.endsOn && ` · ${t('connect.until', { date: formatDate(mentorship.endsOn) })}`}
                    </p>
                </div>
                <Badge>{t(`connect.status.${mentorship.status}`)}</Badge>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {(errors?.mentorship || errors?.message) && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.mentorship ?? errors.message ?? ''} />
                </div>
            )}
            {menteeMinor && (
                <div className="mt-3">
                    <Alert tone="info" title={t('connect.minor.info')} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('connect.agreement.title')}</CardTitle>
                        {mentorship.status === 'proposed' ? (
                            <form
                                className="mt-3 flex flex-col gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    agreement.transform((d) => ({
                                        ...d,
                                        per_month: Number(d.per_month),
                                        months: Number(d.months),
                                        goals: d.goals.filter((g) => g.trim()),
                                    }));
                                    agreement.post(`${base}/agreement`, { preserveScroll: true });
                                }}
                            >
                                {agreement.data.goals.map((g, i) => (
                                    <Field key={i} label={t('connect.agreement.goal', { number: i + 1 })}>
                                        <Input
                                            value={g}
                                            onChange={(e) =>
                                                agreement.setData(
                                                    'goals',
                                                    agreement.data.goals.map((x, j) => (j === i ? e.target.value : x)),
                                                )
                                            }
                                        />
                                    </Field>
                                ))}
                                {agreement.data.goals.length < 3 && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="self-start"
                                        onClick={() => agreement.setData('goals', [...agreement.data.goals, ''])}
                                    >
                                        + {t('connect.agreement.add_goal')}
                                    </Button>
                                )}
                                <div className="grid grid-cols-3 gap-3">
                                    <Field label={t('connect.agreement.per_month')}>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={4}
                                            value={agreement.data.per_month}
                                            onChange={(e) => agreement.setData('per_month', e.target.value)}
                                        />
                                    </Field>
                                    <Field label={t('connect.agreement.months')}>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={6}
                                            value={agreement.data.months}
                                            onChange={(e) => agreement.setData('months', e.target.value)}
                                        />
                                    </Field>
                                    <Field label={t('connect.find.format')}>
                                        <Select
                                            value={agreement.data.format}
                                            onChange={(e) => agreement.setData('format', e.target.value)}
                                        >
                                            {(menteeMinor ? ['hub'] : ['phone', 'video', 'hub']).map((f) => (
                                                <option key={f} value={f}>
                                                    {t(`connect.format.${f}`)}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" variant="secondary">
                                        {t('connect.agreement.propose')}
                                    </Button>
                                    {mentorship.goals.length > 0 && !iAgreed && (
                                        <Button
                                            onClick={() => router.post(`${base}/agree`, {}, { preserveScroll: true })}
                                        >
                                            {t('connect.agreement.agree')}
                                        </Button>
                                    )}
                                </div>
                                {iAgreed && (
                                    <p className="text-fg-muted text-sm">{t('connect.agreement.waiting_other')}</p>
                                )}
                            </form>
                        ) : (
                            <>
                                <ol className="text-fg mt-2 list-decimal pl-5">
                                    {mentorship.goals.map((g) => (
                                        <li key={g}>{g}</li>
                                    ))}
                                </ol>
                                <p className="text-fg-muted mt-2 text-sm">
                                    {t('connect.agreement.summary', {
                                        per_month: mentorship.perMonth,
                                        months: mentorship.months,
                                        format: t(`connect.format.${mentorship.format}`),
                                    })}
                                </p>
                            </>
                        )}
                    </Card>
                    <Card>
                        <CardTitle>{t('connect.session.title')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-3">
                            {sessions.map((s) => (
                                <li key={s.id} className="border-line border-b pb-3 text-sm">
                                    <p className="text-fg font-medium">
                                        {formatDateTime(s.startsAt)} · {s.minutes} min ·{' '}
                                        {t(`connect.format.${s.format}`)}{' '}
                                        <Badge>{t(`connect.session.status_${s.status}`)}</Badge>
                                    </p>
                                    {s.link && s.status === 'scheduled' && (
                                        <a
                                            href={s.link}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            {t('connect.session.join')}
                                        </a>
                                    )}
                                    {s.sharedNotes && (
                                        <p className="text-fg mt-1 whitespace-pre-line">{s.sharedNotes}</p>
                                    )}
                                    {s.actions.length > 0 && (
                                        <p className="text-fg mt-1">
                                            {t('connect.session.to_do', { list: s.actions.join('; ') })}
                                        </p>
                                    )}
                                    {s.privateNotes && (
                                        <p className="text-fg-muted mt-1">
                                            {t('connect.session.your_private', { notes: s.privateNotes })}
                                        </p>
                                    )}
                                    {isMentor && s.status === 'scheduled' && <RecordSession session={s} />}
                                    {!isMentor && s.status === 'held' && s.helpful === null && (
                                        <div className="mt-2 flex items-center gap-2">
                                            <span className="text-fg">{t('connect.session.helpful_q')}</span>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        `/connect/sessions/${s.id}/helpful`,
                                                        { helpful: true },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('common.yes')}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    router.post(
                                                        `/connect/sessions/${s.id}/helpful`,
                                                        { helpful: false },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('common.no')}
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                            {sessions.length === 0 && (
                                <li className="text-fg-muted text-sm">{t('connect.session.none')}</li>
                            )}
                        </ul>
                        {mentorship.status === 'active' && (
                            <form
                                className="mt-4 grid gap-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    book.transform((d) => ({
                                        ...d,
                                        minutes: Number(d.minutes),
                                        link: d.link || null,
                                        hub_id: d.format === 'hub' ? d.hub_id : null,
                                        room: d.room || null,
                                    }));
                                    book.post(`${base}/sessions`, {
                                        preserveScroll: true,
                                        onSuccess: () => book.reset(),
                                    });
                                }}
                            >
                                <Field label={t('work.interview.when')} error={book.errors.starts_at}>
                                    <Input
                                        type="datetime-local"
                                        value={book.data.starts_at}
                                        onChange={(e) => book.setData('starts_at', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('connect.find.format')}>
                                    <Select
                                        value={book.data.format}
                                        onChange={(e) => book.setData('format', e.target.value)}
                                    >
                                        {(menteeMinor ? ['hub'] : ['phone', 'video', 'hub']).map((f) => (
                                            <option key={f} value={f}>
                                                {t(`connect.format.${f}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                {book.data.format === 'video' && (
                                    <Field label={t('connect.session.link')} error={book.errors.link}>
                                        <Input
                                            value={book.data.link}
                                            onChange={(e) => book.setData('link', e.target.value)}
                                        />
                                    </Field>
                                )}
                                {book.data.format === 'hub' && (
                                    <Field label={t('learn.cohort.hub')}>
                                        <Select
                                            value={book.data.hub_id}
                                            onChange={(e) => book.setData('hub_id', e.target.value)}
                                        >
                                            {hubs.map((h) => (
                                                <option key={h.id} value={h.id}>
                                                    {h.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                )}
                                <div className="sm:col-span-2">
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        loading={book.processing}
                                        disabled={!book.data.starts_at}
                                    >
                                        {t('connect.session.book')}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </Card>
                    {!menteeMinor && (
                        <Card>
                            <CardTitle>{t('partner.messages.title')}</CardTitle>
                            <ul className="mt-3 flex flex-col gap-2">
                                {messages.map((m) => (
                                    <li
                                        key={m.id}
                                        className={`rounded-lg p-3 text-sm ${m.mine ? 'bg-primary-soft self-end' : 'bg-surface-muted self-start'}`}
                                    >
                                        <p className="text-fg whitespace-pre-line">{m.body}</p>
                                        {m.held && (
                                            <p className="text-warning-text text-xs">{t('work.messages.held_badge')}</p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {['proposed', 'active'].includes(mentorship.status) && (
                                <form
                                    className="mt-3 flex gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        router.post(
                                            `${base}/messages`,
                                            { body: message },
                                            { preserveScroll: true, onSuccess: () => setMessage('') },
                                        );
                                    }}
                                >
                                    <label className="sr-only" htmlFor="connect-message">
                                        {t('partner.messages.write')}
                                    </label>
                                    <textarea
                                        id="connect-message"
                                        rows={2}
                                        maxLength={2000}
                                        value={message}
                                        onChange={(e) => setMessage(e.target.value)}
                                        className="border-line bg-surface text-fg flex-1 rounded-lg border p-2 text-sm"
                                    />
                                    <Button type="submit" disabled={!message.trim()}>
                                        {t('work.messages.send')}
                                    </Button>
                                </form>
                            )}
                            <p className="text-fg-muted mt-2 text-xs">{t('connect.messages.safety')}</p>
                        </Card>
                    )}
                </div>
                <div className="flex flex-col gap-4">
                    <Card>
                        <ReportConcern about={otherId} mentorshipId={mentorship.id} />
                    </Card>
                    {mentorship.status === 'active' && !mentorship.renewed && (
                        <Button variant="secondary" onClick={() => router.post(`${base}/renew`)}>
                            {t('connect.agreement.renew')}
                        </Button>
                    )}
                    {mentorship.status !== 'ended' &&
                        (ending ? (
                            <Card>
                                <form
                                    className="flex flex-col gap-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        end.post(`${base}/end`);
                                    }}
                                >
                                    <fieldset>
                                        <legend className="text-fg text-sm font-semibold">
                                            {t('connect.end.goals_met')}
                                        </legend>
                                        {mentorship.goals.map((g, i) => (
                                            <Checkbox
                                                key={g}
                                                label={g}
                                                checked={end.data.goals_met[i] ?? false}
                                                onCheckedChange={(c) =>
                                                    end.setData(
                                                        'goals_met',
                                                        end.data.goals_met.map((x, j) => (j === i ? c === true : x)),
                                                    )
                                                }
                                            />
                                        ))}
                                    </fieldset>
                                    <Field label={t('connect.end.reason')} hint={t('connect.end.reason_hint')}>
                                        <Input
                                            value={end.data.reason}
                                            onChange={(e) => end.setData('reason', e.target.value)}
                                        />
                                    </Field>
                                    <Field label={t('connect.end.feedback')}>
                                        <Textarea
                                            rows={2}
                                            value={end.data.feedback}
                                            onChange={(e) => end.setData('feedback', e.target.value)}
                                        />
                                    </Field>
                                    <Button type="submit" variant="danger" disabled={!end.data.reason.trim()}>
                                        {t('connect.end.confirm')}
                                    </Button>
                                </form>
                            </Card>
                        ) : (
                            <Button variant="ghost" onClick={() => setEnding(true)}>
                                {t('connect.end.title')}
                            </Button>
                        ))}
                </div>
            </div>
        </AppLayout>
    );
}
