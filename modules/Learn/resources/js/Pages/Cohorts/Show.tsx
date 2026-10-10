import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import type { CohortCard } from './Index';

interface Member {
    id: string;
    name: string;
    status: string;
    progress: number;
    completed: boolean;
    attendance: number | null;
    stuck: boolean;
    nudgedAt: string | null;
}

export default function CohortShow({
    cohort,
    members,
    sessions,
    joinUrl,
}: {
    cohort: CohortCard;
    members: Member[];
    sessions: { id: number; title: string; startsAt: string; cancelled: boolean; attended: number | null }[];
    joinUrl: string;
}) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [selected, setSelected] = useState<string[]>([]);
    const [message, setMessage] = useState('');
    const add = useForm({ phone: '' });
    const session = useForm({ title: '', starts_at: '', minutes: '120', room: '' });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={cohort.name} />
            <Link href="/learn/cohorts" className="text-primary text-sm font-semibold hover:underline">
                ← {t('learn.cohort.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{cohort.name}</h1>
                    <p className="text-fg-muted">
                        {cohort.course} · {cohort.hub ?? t('learn.cohort.online')}
                    </p>
                </div>
                <Badge tone={cohort.status === 'open' ? 'success' : 'warning'}>
                    {t(`learn.cohort.status.${cohort.status}`)}
                </Badge>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <Card className="mt-4">
                <p className="text-fg">
                    {t('learn.cohort.join_code')}:{' '}
                    <span className="font-mono text-xl font-bold tracking-widest">{cohort.code}</span>
                </p>
                <p className="text-fg-muted text-sm break-all">{joinUrl}</p>
            </Card>
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <CardTitle>
                        {t('learn.cohort.members', { members: cohort.members, capacity: cohort.capacity })}
                    </CardTitle>
                    <ul className="mt-3 flex flex-col gap-1">
                        {members.map((m) => (
                            <li
                                key={m.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm"
                            >
                                <Checkbox
                                    label={m.name}
                                    checked={selected.includes(m.id)}
                                    onCheckedChange={(c) =>
                                        setSelected(
                                            c === true ? [...selected, m.id] : selected.filter((x) => x !== m.id),
                                        )
                                    }
                                />
                                <span className="text-fg-muted flex flex-wrap items-center gap-2">
                                    {m.status === 'waiting' && (
                                        <Badge tone="warning">{t('learn.cohort.waiting')}</Badge>
                                    )}
                                    {m.stuck && <Badge tone="danger">{t('learn.cohort.stuck')}</Badge>}
                                    {m.completed && <Badge tone="success">{t('learn.my.done_badge')}</Badge>}
                                    {t('learn.my.progress', { progress: m.progress })}
                                    {m.attendance !== null &&
                                        ` · ${t('learn.cohort.attendance', { percent: m.attendance })}`}
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(`/learn/cohorts/${cohort.id}/members/${m.id}`, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {t('hubops.settings.remove')}
                                    </Button>
                                </span>
                            </li>
                        ))}
                        {members.length === 0 && <li className="text-fg-muted">{t('learn.cohort.no_members')}</li>}
                    </ul>
                    {selected.length > 0 && (
                        <div className="mt-4 flex flex-col gap-2">
                            <Field label={t('learn.cohort.nudge_label', { count: selected.length })}>
                                <Textarea
                                    rows={2}
                                    maxLength={300}
                                    value={message}
                                    onChange={(e) => setMessage(e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button
                                    size="sm"
                                    disabled={message.trim().length < 5}
                                    onClick={() =>
                                        router.post(
                                            `/learn/cohorts/${cohort.id}/nudge`,
                                            { users: selected, message },
                                            { preserveScroll: true, onSuccess: () => setSelected([]) },
                                        )
                                    }
                                >
                                    {t('learn.cohort.nudge')}
                                </Button>
                            </div>
                        </div>
                    )}
                    <form
                        className="border-line mt-4 flex flex-wrap items-end gap-2 border-t pt-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            add.post(`/learn/cohorts/${cohort.id}/members`, {
                                preserveScroll: true,
                                onSuccess: () => add.reset(),
                            });
                        }}
                    >
                        <Field
                            label={t('learn.cohort.add_member')}
                            error={add.errors.phone ?? errors?.phone}
                            className="flex-1"
                        >
                            <PhoneInput value={add.data.phone} onChange={(_, raw) => add.setData('phone', raw)} />
                        </Field>
                        <Button type="submit" variant="secondary" loading={add.processing}>
                            {t('work.add')}
                        </Button>
                    </form>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('learn.cohort.sessions')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2 text-sm">
                        {sessions.map((s) => (
                            <li key={s.id}>
                                <span className="text-fg font-medium">{s.title}</span>{' '}
                                <span className="text-fg-muted">{formatDateTime(s.startsAt)}</span>
                                {s.attended !== null && (
                                    <span className="text-fg-muted">
                                        {' '}
                                        · {t('learn.cohort.attended', { count: s.attended })}
                                    </span>
                                )}
                            </li>
                        ))}
                        {sessions.length === 0 && <li className="text-fg-muted">{t('learn.cohort.no_sessions')}</li>}
                    </ul>
                    {cohort.status === 'open' && (
                        <form
                            className="mt-4 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                session.transform((d) => ({ ...d, minutes: Number(d.minutes), room: d.room || null }));
                                session.post(`/learn/cohorts/${cohort.id}/sessions`, {
                                    preserveScroll: true,
                                    onSuccess: () => session.reset(),
                                });
                            }}
                        >
                            <Field label={t('learn.cohort.session_title')} error={session.errors.title}>
                                <Input
                                    value={session.data.title}
                                    onChange={(e) => session.setData('title', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.interview.when')} error={session.errors.starts_at}>
                                <Input
                                    type="datetime-local"
                                    value={session.data.starts_at}
                                    onChange={(e) => session.setData('starts_at', e.target.value)}
                                />
                            </Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('learn.author.minutes')}>
                                    <Input
                                        type="number"
                                        value={session.data.minutes}
                                        onChange={(e) => session.setData('minutes', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('learn.cohort.room')}>
                                    <Input
                                        value={session.data.room}
                                        onChange={(e) => session.setData('room', e.target.value)}
                                    />
                                </Field>
                            </div>
                            <Button type="submit" variant="secondary" loading={session.processing}>
                                {t('learn.cohort.add_session')}
                            </Button>
                        </form>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
