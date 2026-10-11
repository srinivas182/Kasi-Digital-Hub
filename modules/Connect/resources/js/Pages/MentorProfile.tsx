import { Head, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    minor: boolean;
    mentor: {
        headline: string;
        bio: string | null;
        expertise: string[];
        goals: string[];
        languages: string[];
        formats: string[];
        hub_id: string | null;
        availability: string | null;
        capacity: number;
        status: string;
        status_reason: string | null;
        minors_ok: boolean;
        missing: string[];
        hours: number;
    } | null;
    requests: { id: string; name: string; goal: string; message: string | null }[];
    hubs: { id: string; name: string }[];
}

export default function MentorProfile({ minor, mentor, requests, hubs }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({
        headline: mentor?.headline ?? '',
        bio: mentor?.bio ?? '',
        expertise: (mentor?.expertise ?? []).join(', '),
        goals: mentor?.goals ?? ['job'],
        languages: (mentor?.languages ?? ['English']).join(', '),
        formats: mentor?.formats ?? ['phone'],
        hub_id: mentor?.hub_id ?? '',
        availability: mentor?.availability ?? '',
        capacity: String(mentor?.capacity ?? 3),
    });
    const group = useForm({
        title: '',
        hub_id: mentor?.hub_id ?? hubs[0]?.id ?? '',
        starts_at: '',
        minutes: '90',
        capacity: '30',
    });
    const list = (s: string) =>
        s
            .split(',')
            .map((x) => x.trim())
            .filter(Boolean);
    const toggle = (key: 'goals' | 'formats', v: string, on: boolean) =>
        form.setData(key, on ? [...form.data[key], v] : form.data[key].filter((x) => x !== v));

    if (minor) {
        return (
            <AppLayout userName={auth.user?.name}>
                <Head title={t('nav.connect.be_mentor')} />
                <Alert tone="info" title={t('connect.mentor.adults')} />
            </AppLayout>
        );
    }
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('nav.connect.be_mentor')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('nav.connect.be_mentor')}</h1>
                {mentor && (
                    <Badge tone={mentor.status === 'approved' ? 'success' : 'warning'}>
                        {t(`connect.mentor.status_${mentor.status}`)}
                    </Badge>
                )}
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {mentor && mentor.status === 'applied' && (
                <div className="mt-3">
                    <Alert tone="info" title={t('connect.mentor.vetting')}>
                        <ul className="mt-1 list-disc pl-5 text-sm">
                            {mentor.missing.map((m) => (
                                <li key={m}>{t(`connect.check.${m}`)}</li>
                            ))}
                        </ul>
                    </Alert>
                </div>
            )}
            {mentor?.status_reason && (
                <div className="mt-3">
                    <Alert tone="warning" title={mentor.status_reason} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <form
                        className="grid gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({
                                ...d,
                                expertise: list(d.expertise),
                                languages: list(d.languages),
                                capacity: Number(d.capacity),
                                hub_id: d.hub_id || null,
                            }));
                            form.post('/connect/mentor', { preserveScroll: true });
                        }}
                    >
                        <Field
                            label={t('connect.mentor.headline')}
                            error={form.errors.headline ?? errors?.headline}
                            className="md:col-span-2"
                            required
                        >
                            <Input
                                value={form.data.headline}
                                onChange={(e) => form.setData('headline', e.target.value)}
                            />
                        </Field>
                        <Field label={t('connect.mentor.bio')} className="md:col-span-2">
                            <Textarea
                                rows={3}
                                value={form.data.bio}
                                onChange={(e) => form.setData('bio', e.target.value)}
                            />
                        </Field>
                        <Field
                            label={t('connect.mentor.expertise')}
                            hint={t('connect.mentor.comma_hint')}
                            error={form.errors.expertise}
                        >
                            <Input
                                value={form.data.expertise}
                                onChange={(e) => form.setData('expertise', e.target.value)}
                            />
                        </Field>
                        <Field label={t('connect.mentor.languages')} hint={t('connect.mentor.comma_hint')}>
                            <Input
                                value={form.data.languages}
                                onChange={(e) => form.setData('languages', e.target.value)}
                            />
                        </Field>
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('connect.mentor.goals')}</legend>
                            {['job', 'business', 'career', 'confidence'].map((g) => (
                                <Checkbox
                                    key={g}
                                    label={t(`connect.goal.${g}`)}
                                    checked={form.data.goals.includes(g)}
                                    onCheckedChange={(c) => toggle('goals', g, c === true)}
                                />
                            ))}
                        </fieldset>
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('connect.find.format')}</legend>
                            {['phone', 'video', 'hub'].map((f) => (
                                <Checkbox
                                    key={f}
                                    label={t(`connect.format.${f}`)}
                                    checked={form.data.formats.includes(f)}
                                    onCheckedChange={(c) => toggle('formats', f, c === true)}
                                />
                            ))}
                        </fieldset>
                        <Field label={t('learn.cohort.hub')}>
                            <Select value={form.data.hub_id} onChange={(e) => form.setData('hub_id', e.target.value)}>
                                <option value="">-</option>
                                {hubs.map((h) => (
                                    <option key={h.id} value={h.id}>
                                        {h.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('connect.mentor.capacity')}>
                            <Input
                                type="number"
                                min={1}
                                max={5}
                                value={form.data.capacity}
                                onChange={(e) => form.setData('capacity', e.target.value)}
                            />
                        </Field>
                        <Field label={t('connect.mentor.availability')} className="md:col-span-2">
                            <Input
                                value={form.data.availability}
                                onChange={(e) => form.setData('availability', e.target.value)}
                            />
                        </Field>
                        <p className="text-fg-muted text-sm md:col-span-2">{t('connect.mentor.vetting_lead')}</p>
                        <div>
                            <Button type="submit" loading={form.processing}>
                                {mentor ? t('work.save') : t('connect.mentor.apply')}
                            </Button>
                        </div>
                    </form>
                </Card>
                <div className="flex flex-col gap-6">
                    {mentor?.status === 'approved' && (
                        <Card>
                            <CardTitle>{t('connect.mentor.requests')}</CardTitle>
                            <ul className="mt-2 flex flex-col gap-3 text-sm">
                                {requests.map((r) => (
                                    <li key={r.id}>
                                        <p className="text-fg font-medium">
                                            {r.name} · {t(`connect.goal.${r.goal}`)}
                                        </p>
                                        {r.message && <p className="text-fg-muted">{r.message}</p>}
                                        <div className="mt-1 flex gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.post(`/connect/requests/${r.id}/answer`, { accept: true })
                                                }
                                            >
                                                {t('connect.mentor.accept')}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.post(
                                                        `/connect/requests/${r.id}/answer`,
                                                        { accept: false },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('connect.mentor.decline')}
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                                {requests.length === 0 && (
                                    <li className="text-fg-muted">{t('connect.mentor.no_requests')}</li>
                                )}
                            </ul>
                        </Card>
                    )}
                    {mentor && (
                        <Card>
                            <CardTitle>{t('connect.mentor.hours', { hours: mentor.hours })}</CardTitle>
                            {mentor.hours >= 1 && (
                                <a
                                    href="/connect/mentor/hours"
                                    className={buttonVariants({ size: 'sm', variant: 'secondary', className: 'mt-2' })}
                                >
                                    {t('connect.mentor.certificate')}
                                </a>
                            )}
                        </Card>
                    )}
                    {mentor?.status === 'approved' && (
                        <Card>
                            <CardTitle>{t('connect.group.title')}</CardTitle>
                            <form
                                className="mt-2 flex flex-col gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    group.transform((d) => ({
                                        ...d,
                                        minutes: Number(d.minutes),
                                        capacity: Number(d.capacity),
                                    }));
                                    group.post('/connect/mentor/group-sessions', {
                                        preserveScroll: true,
                                        onSuccess: () => group.reset(),
                                    });
                                }}
                            >
                                <Field label={t('connect.group.name')} error={group.errors.title}>
                                    <Input
                                        value={group.data.title}
                                        onChange={(e) => group.setData('title', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('learn.cohort.hub')}>
                                    <Select
                                        value={group.data.hub_id}
                                        onChange={(e) => group.setData('hub_id', e.target.value)}
                                    >
                                        {hubs.map((h) => (
                                            <option key={h.id} value={h.id}>
                                                {h.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('work.interview.when')} error={group.errors.starts_at}>
                                    <Input
                                        type="datetime-local"
                                        value={group.data.starts_at}
                                        onChange={(e) => group.setData('starts_at', e.target.value)}
                                    />
                                </Field>
                                <div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="secondary"
                                        disabled={!group.data.title.trim() || !group.data.starts_at}
                                    >
                                        {t('connect.group.create')}
                                    </Button>
                                </div>
                            </form>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
