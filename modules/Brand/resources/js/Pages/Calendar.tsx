import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Post {
    id: number;
    channel: string;
    planned_at: string;
    text: string;
    design_id: number | null;
    template: string | null;
    status: string;
    suggested: boolean;
}

interface Props {
    business: { id: string; name: string };
    month: string;
    channels: string[];
    templates: string[];
    posts: Post[];
    designs: { id: number; title: string; template: string }[];
    moments: { date: string; key: string; template: string }[];
}

function shift(month: string, by: number): string {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(Date.UTC(y ?? 2026, (m ?? 1) - 1 + by, 1));
    return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
}

/** The content calendar (S31): plan the month, get reminders, make the design. No automatic posting. */
export default function CalendarPage({ month, channels, posts, designs, moments }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const add = useForm({ channel: 'whatsapp', planned_at: `${month}-01T08:00`, text: '', design_id: '' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('brand.calendar.title')} />
            <Link href="/brand" className="text-primary text-sm font-semibold hover:underline">
                ← {t('brand.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <h1 className="text-fg text-2xl font-bold">{t('brand.calendar.title')}</h1>
                <span className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={t('brand.calendar.previous')}
                        onClick={() => router.get('/brand/calendar', { month: shift(month, -1) })}
                    >
                        ←
                    </Button>
                    <span className="text-fg font-semibold">{month}</span>
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={t('brand.calendar.next')}
                        onClick={() => router.get('/brand/calendar', { month: shift(month, 1) })}
                    >
                        →
                    </Button>
                </span>
            </div>
            <p className="text-fg-muted mt-1 text-sm">{t('brand.calendar.lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.brand && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.brand} />
                </div>
            )}
            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="flex flex-col gap-3">
                    <Card>
                        <CardTitle>{t('brand.calendar.plan_title')}</CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">
                            {moments.length > 0
                                ? t('brand.calendar.moments', {
                                      list: moments
                                          .map((m) => `${formatDate(m.date)} ${t(`brand.moment.${m.key}`)}`)
                                          .join(', '),
                                  })
                                : t('brand.calendar.no_moments')}
                        </p>
                        <Button
                            className="mt-2"
                            onClick={() => router.post('/brand/calendar/plan', { month }, { preserveScroll: true })}
                        >
                            {t('brand.calendar.plan')}
                        </Button>
                        <p className="text-fg-muted mt-1 text-xs">{t('brand.calendar.plan_note')}</p>
                    </Card>
                    <ul className="flex flex-col gap-3">
                        {posts.map((p) => (
                            <li key={p.id}>
                                <Card>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-fg font-semibold">
                                            {formatDateTime(p.planned_at)} · {t(`brand.channel.${p.channel}`)}
                                        </span>
                                        <span className="flex gap-1">
                                            {p.suggested && (
                                                <Badge tone="neutral">{t('brand.calendar.suggested')}</Badge>
                                            )}
                                            <Badge
                                                tone={
                                                    p.status === 'posted'
                                                        ? 'success'
                                                        : p.status === 'skipped'
                                                          ? 'neutral'
                                                          : 'warning'
                                                }
                                            >
                                                {t(`brand.calendar.status.${p.status}`)}
                                            </Badge>
                                        </span>
                                    </div>
                                    <p className="text-fg mt-1 text-sm whitespace-pre-line">{p.text}</p>
                                    <div className="mt-2 flex flex-wrap gap-3 text-sm">
                                        {p.status !== 'posted' && (
                                            <button
                                                type="button"
                                                className="text-primary font-semibold underline"
                                                onClick={() =>
                                                    router.post(
                                                        `/brand/calendar/posts/${p.id}/mark`,
                                                        { status: 'posted' },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('brand.calendar.mark_posted')}
                                            </button>
                                        )}
                                        <a
                                            href={`https://wa.me/?text=${encodeURIComponent(p.text)}`}
                                            rel="noopener"
                                            className="text-primary underline"
                                        >
                                            {t('brand.calendar.share')}
                                        </a>
                                        {p.design_id ? (
                                            <Link
                                                href={`/brand/make/${designs.find((d) => d.id === p.design_id)?.template ?? 'square'}/${p.design_id}`}
                                                className="text-primary underline"
                                            >
                                                {t('brand.calendar.open_design')}
                                            </Link>
                                        ) : (
                                            p.template && (
                                                <Link
                                                    href={`/brand/make/${p.template}`}
                                                    className="text-primary underline"
                                                >
                                                    {t('brand.calendar.make_design')}
                                                </Link>
                                            )
                                        )}
                                        {p.status === 'planned' && (
                                            <button
                                                type="button"
                                                className="text-fg-muted underline"
                                                onClick={() =>
                                                    router.post(
                                                        `/brand/calendar/posts/${p.id}/mark`,
                                                        { status: 'skipped' },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('brand.calendar.skip')}
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            className="text-danger-text underline"
                                            onClick={() =>
                                                router.post(
                                                    `/brand/calendar/posts/${p.id}/mark`,
                                                    { status: 'delete' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('brand.calendar.delete')}
                                        </button>
                                    </div>
                                </Card>
                            </li>
                        ))}
                        {posts.length === 0 && <li className="text-fg-muted">{t('brand.calendar.none')}</li>}
                    </ul>
                </div>
                <Card className="h-fit">
                    <CardTitle>{t('brand.calendar.add')}</CardTitle>
                    <form
                        className="mt-2 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            add.transform((d) => ({ ...d, design_id: d.design_id ? Number(d.design_id) : null }));
                            add.post('/brand/calendar/posts', {
                                preserveScroll: true,
                                onSuccess: () => add.reset('text'),
                            });
                        }}
                    >
                        <Field label={t('brand.calendar.where')}>
                            <Select value={add.data.channel} onChange={(e) => add.setData('channel', e.target.value)}>
                                {channels.map((c) => (
                                    <option key={c} value={c}>
                                        {t(`brand.channel.${c}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('brand.calendar.when')}>
                            <Input
                                type="datetime-local"
                                value={add.data.planned_at}
                                onChange={(e) => add.setData('planned_at', e.target.value)}
                            />
                        </Field>
                        <Field label={t('brand.calendar.text')}>
                            <Textarea
                                rows={3}
                                maxLength={500}
                                value={add.data.text}
                                onChange={(e) => add.setData('text', e.target.value)}
                            />
                        </Field>
                        <Field label={t('brand.calendar.design')}>
                            <Select
                                value={add.data.design_id}
                                onChange={(e) => add.setData('design_id', e.target.value)}
                            >
                                <option value="">{t('brand.calendar.no_design')}</option>
                                {designs.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.title}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div>
                            <Button type="submit" size="sm" disabled={!add.data.text.trim()} loading={add.processing}>
                                {t('brand.calendar.save')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
