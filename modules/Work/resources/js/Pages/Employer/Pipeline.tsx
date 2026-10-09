import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Field, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

import { MatchScore } from '../../components/MatchScore';

interface Card {
    id: string;
    stage: string;
    score: number | null;
    reference: string;
    blind: boolean;
    name: string;
    phone: string | null;
    headline: string | null;
    appliedAt: string;
    unread: number;
}

interface Props {
    listing: { id: string; title: string; status: string; blind: boolean };
    stages: string[];
    reasons: string[];
    applications: Card[];
}

/** Board of columns on wide screens; one stage at a time on phones. Bulk moves for both. */
export default function Pipeline({ listing, stages, reasons, applications }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    // Phones show one stage at a time: start with the first stage that has applicants.
    const [stage, setStage] = useState(() => stages.find((s) => applications.some((a) => a.stage === s)) ?? 'new');
    const move = useForm({ ids: [] as string[], stage: 'shortlisted', message: '', reason: '' });
    const toggle = (id: string) =>
        move.setData(
            'ids',
            move.data.ids.includes(id) ? move.data.ids.filter((x) => x !== id) : [...move.data.ids, id],
        );

    const card = (a: Card) => (
        <li key={a.id}>
            <Card className="p-3">
                <div className="flex items-start gap-2">
                    <Checkbox
                        label=""
                        aria-label={a.name}
                        checked={move.data.ids.includes(a.id)}
                        onCheckedChange={() => toggle(a.id)}
                    />
                    <div className="min-w-0 flex-1">
                        <Link
                            href={`/work/employer/listings/${listing.id}/applicants/${a.id}`}
                            className="text-primary block truncate font-semibold hover:underline"
                        >
                            {a.name}
                        </Link>
                        {a.headline && <p className="text-fg truncate text-xs">{a.headline}</p>}
                        <div className="mt-1 flex flex-wrap items-center gap-1">
                            {a.score !== null && <MatchScore score={a.score} />}
                            {a.unread > 0 && (
                                <Badge tone="warning">{t('work.pipeline.unread', { count: a.unread })}</Badge>
                            )}
                        </div>
                    </div>
                </div>
            </Card>
        </li>
    );

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={`${t('work.pipeline.title')} - ${listing.title}`} />
            <Link
                href={`/work/employer/listings/${listing.id}/edit`}
                className="text-primary text-sm font-semibold hover:underline"
            >
                ← {listing.title}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('work.pipeline.title')}</h1>
            {listing.blind && <p className="text-fg-muted text-sm">{t('work.pipeline.blind')}</p>}
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {applications.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<Users className="size-8" aria-hidden />} title={t('work.pipeline.none')} />
                </div>
            ) : (
                <>
                    <form
                        className="rounded-card border-line bg-surface mt-4 flex flex-col gap-3 border p-3 md:flex-row md:items-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            move.transform((d) => ({ ...d, reason: d.reason || null, message: d.message || null }));
                            move.post(`/work/employer/listings/${listing.id}/applicants/move`, {
                                preserveScroll: true,
                                onSuccess: () => move.reset(),
                            });
                        }}
                    >
                        <Field label={t('work.pipeline.move_to')}>
                            <Select value={move.data.stage} onChange={(e) => move.setData('stage', e.target.value)}>
                                {stages.map((s) => (
                                    <option key={s} value={s}>
                                        {t(`work.stage.${s}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        {move.data.stage === 'unsuccessful' && (
                            <>
                                <Field label={t('work.pipeline.reason')}>
                                    <Select
                                        value={move.data.reason}
                                        onChange={(e) => move.setData('reason', e.target.value)}
                                    >
                                        <option value="">-</option>
                                        {reasons.map((r) => (
                                            <option key={r} value={r}>
                                                {t(`work.pipeline.reason.${r}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('work.pipeline.unsuccessful_message')} className="flex-1">
                                    <Textarea
                                        rows={1}
                                        maxLength={500}
                                        value={move.data.message}
                                        onChange={(e) => move.setData('message', e.target.value)}
                                    />
                                </Field>
                            </>
                        )}
                        <Button type="submit" loading={move.processing} disabled={move.data.ids.length === 0}>
                            {t('work.pipeline.move')} ({move.data.ids.length})
                        </Button>
                    </form>

                    {/* Phones and tablets: one stage at a time */}
                    <div className="mt-4 lg:hidden">
                        <div
                            className="flex gap-2 overflow-x-auto pb-1"
                            role="group"
                            aria-label={t('work.pipeline.title')}
                        >
                            {stages.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    aria-pressed={stage === s}
                                    onClick={() => setStage(s)}
                                    className={cn(
                                        'min-h-11 shrink-0 rounded-full border px-4 text-sm font-semibold',
                                        stage === s
                                            ? 'bg-primary text-primary-fg border-transparent'
                                            : 'border-line text-fg',
                                    )}
                                >
                                    {t(`work.stage.${s}`)} ({applications.filter((a) => a.stage === s).length})
                                </button>
                            ))}
                        </div>
                        <ul className="mt-3 flex flex-col gap-2">
                            {applications.filter((a) => a.stage === stage).map(card)}
                        </ul>
                    </div>

                    {/* Wide screens: the board */}
                    <div className="mt-4 hidden gap-3 lg:grid lg:grid-cols-6">
                        {stages.map((s) => (
                            <section key={s} aria-labelledby={`col-${s}`} className="bg-surface-muted rounded-lg p-2">
                                <h2 id={`col-${s}`} className="text-fg mb-2 text-sm font-bold">
                                    {t(`work.stage.${s}`)} ({applications.filter((a) => a.stage === s).length})
                                </h2>
                                <ul className="flex flex-col gap-2">
                                    {applications.filter((a) => a.stage === s).map(card)}
                                </ul>
                            </section>
                        ))}
                    </div>
                </>
            )}
        </AppLayout>
    );
}
