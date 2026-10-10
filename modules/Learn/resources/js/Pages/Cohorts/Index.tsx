import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export interface CohortCard {
    id: string;
    name: string;
    code: string;
    course: string;
    hub: string | null;
    status: string;
    reason: string | null;
    startsOn: string;
    endsOn: string;
    capacity: number;
    members: number;
    waiting: number;
}

export default function CohortsIndex({
    cohorts,
    courses,
    hubs,
}: {
    cohorts: CohortCard[];
    courses: { id: string; title: string }[];
    hubs: { id: string; name: string }[];
}) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        course_id: courses[0]?.id ?? '',
        name: '',
        hub_id: '',
        starts_on: today,
        ends_on: '',
        capacity: '25',
    });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.cohort.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('learn.cohort.title')}</h1>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <ul className="flex flex-col gap-3">
                    {cohorts.map((c) => (
                        <li key={c.id}>
                            <Link href={`/learn/cohorts/${c.id}`} className="block">
                                <Card className="hover:bg-surface-muted">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-fg font-semibold">{c.name}</span>
                                        <Badge
                                            tone={
                                                c.status === 'open'
                                                    ? 'success'
                                                    : c.status === 'pending_hub'
                                                      ? 'warning'
                                                      : 'neutral'
                                            }
                                        >
                                            {t(`learn.cohort.status.${c.status}`)}
                                        </Badge>
                                    </div>
                                    <p className="text-fg-muted text-sm">
                                        {c.course} · {c.hub ?? t('learn.cohort.online')} · {formatDate(c.startsOn)} -{' '}
                                        {formatDate(c.endsOn)}
                                    </p>
                                    <p className="text-fg-muted text-sm">
                                        {t('learn.cohort.places', {
                                            members: c.members,
                                            capacity: c.capacity,
                                            waiting: c.waiting,
                                        })}
                                    </p>
                                </Card>
                            </Link>
                        </li>
                    ))}
                    {cohorts.length === 0 && <li className="text-fg-muted">{t('learn.cohort.none')}</li>}
                </ul>
                <Card className="h-fit">
                    <CardTitle>{t('learn.cohort.new')}</CardTitle>
                    <form
                        className="mt-3 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({ ...d, hub_id: d.hub_id || null, capacity: Number(d.capacity) }));
                            form.post('/learn/cohorts');
                        }}
                    >
                        <Field label={t('learn.cohort.course')} error={form.errors.course_id}>
                            <Select
                                value={form.data.course_id}
                                onChange={(e) => form.setData('course_id', e.target.value)}
                            >
                                {courses.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.title}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('learn.cohort.name')} error={form.errors.name}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field
                            label={t('learn.cohort.hub')}
                            hint={t('learn.cohort.hub_hint')}
                            error={form.errors.hub_id}
                        >
                            <Select value={form.data.hub_id} onChange={(e) => form.setData('hub_id', e.target.value)}>
                                <option value="">{t('learn.cohort.online')}</option>
                                {hubs.map((h) => (
                                    <option key={h.id} value={h.id}>
                                        {h.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('learn.cohort.starts')} error={form.errors.starts_on}>
                                <Input
                                    type="date"
                                    value={form.data.starts_on}
                                    onChange={(e) => form.setData('starts_on', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.cohort.ends')} error={form.errors.ends_on}>
                                <Input
                                    type="date"
                                    value={form.data.ends_on}
                                    onChange={(e) => form.setData('ends_on', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Field label={t('learn.cohort.capacity')} error={form.errors.capacity}>
                            <Input
                                type="number"
                                min={1}
                                value={form.data.capacity}
                                onChange={(e) => form.setData('capacity', e.target.value)}
                            />
                        </Field>
                        <Button
                            type="submit"
                            loading={form.processing}
                            disabled={!form.data.name.trim() || !form.data.ends_on}
                        >
                            {t('learn.cohort.create')}
                        </Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
