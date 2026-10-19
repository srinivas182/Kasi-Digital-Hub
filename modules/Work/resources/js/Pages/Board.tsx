import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Badge, Card } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Job {
    id: string;
    title: string;
    employer: string;
    place: string | null;
    type: string;
    payMin: number;
    payMax: number | null;
    payPeriod: string;
    closes: string;
    posted: string | null;
}

interface Props {
    jobs: { data: Job[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: { q?: string; province?: string | number; municipality?: string | number; type?: string };
    provinces: { id: number; name: string }[];
    municipalities: { id: number; name: string }[];
}

const TYPES = ['full_time', 'part_time', 'temporary', 'piece_work', 'learnership', 'internship'];

/** Public job board (S23): anyone can browse open jobs; applying asks them to sign up or sign in. */
export default function Board({ jobs, filters, provinces, municipalities }: Props) {
    const { t } = useTranslation();
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (next: Record<string, string | number | undefined>) => {
        const merged = { ...filters, ...next };
        router.get('/jobs', Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== undefined && v !== '')), {
            preserveScroll: true,
            preserveState: true,
        });
    };
    return (
        <PublicLayout>
            <Head title={t('work.board.title')} />
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <h1 className="text-fg text-3xl font-bold">{t('work.board.title')}</h1>
                <p className="text-fg-muted mt-1">{t('work.board.lead')}</p>
                <form
                    role="search"
                    className="mt-6 grid gap-3 sm:grid-cols-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({ q, municipality: filters.municipality });
                    }}
                >
                    <Field label={t('work.board.search')} className="sm:col-span-2">
                        <Input value={q} onChange={(e) => setQ(e.target.value)} />
                    </Field>
                    <Field label={t('work.board.province')}>
                        <Select
                            value={String(filters.province ?? '')}
                            onChange={(e) => apply({ province: e.target.value || undefined, municipality: undefined })}
                        >
                            <option value="">{t('work.board.anywhere')}</option>
                            {provinces.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('work.board.type')}>
                        <Select
                            value={filters.type ?? ''}
                            onChange={(e) => apply({ type: e.target.value || undefined })}
                        >
                            <option value="">{t('work.board.any_type')}</option>
                            {TYPES.map((type) => (
                                <option key={type} value={type}>
                                    {t(`work.type.${type}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {municipalities.length > 0 && (
                        <Field label={t('work.board.city')} className="sm:col-span-2">
                            <Select
                                value={String(filters.municipality ?? '')}
                                onChange={(e) => apply({ municipality: e.target.value || undefined })}
                            >
                                <option value="">{t('work.board.whole_province')}</option>
                                {municipalities.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    )}
                    <div className="flex items-end">
                        <Button type="submit">{t('work.board.find')}</Button>
                    </div>
                </form>
                <p className="text-fg-muted mt-6 text-sm" role="status">
                    {t('work.board.count', { count: jobs.total })}
                </p>
                <ul className="mt-2 flex flex-col gap-3">
                    {jobs.data.map((j) => (
                        <li key={j.id}>
                            <Card>
                                <Link
                                    href={`/jobs/${j.id}`}
                                    className="text-primary text-lg font-semibold hover:underline"
                                >
                                    {j.title}
                                </Link>
                                <p className="text-fg">
                                    {j.employer}
                                    {j.place ? ` · ${j.place}` : ''}
                                </p>
                                <p className="text-fg-muted mt-1 flex flex-wrap items-center gap-2 text-sm">
                                    <Badge tone="neutral">{t(`work.type.${j.type}`)}</Badge>
                                    <span>
                                        {formatMoney(j.payMin)}
                                        {j.payMax ? ` - ${formatMoney(j.payMax)}` : ''}{' '}
                                        {t(`work.pay.per_${j.payPeriod}`)}
                                    </span>
                                    <span>{t('work.board.closes', { date: formatDate(j.closes) })}</span>
                                </p>
                            </Card>
                        </li>
                    ))}
                    {jobs.data.length === 0 && <li className="text-fg-muted">{t('work.board.none')}</li>}
                </ul>
                {jobs.links.length > 3 && (
                    <nav className="mt-6 flex flex-wrap gap-2" aria-label={t('work.board.pages')}>
                        {jobs.links.map((l, i) =>
                            l.url ? (
                                <Link
                                    key={i}
                                    href={l.url}
                                    preserveScroll
                                    className={`rounded border px-3 py-1 text-sm ${l.active ? 'bg-primary text-on-primary' : 'border-line text-fg'}`}
                                    aria-current={l.active ? 'page' : undefined}
                                >
                                    <span dangerouslySetInnerHTML={{ __html: l.label }} />
                                </Link>
                            ) : null,
                        )}
                    </nav>
                )}
                <p className="text-fg mt-8">
                    <Link href="/signup" className="text-primary font-semibold hover:underline">
                        {t('work.board.signup')}
                    </Link>
                </p>
            </div>
        </PublicLayout>
    );
}
