import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

type Cell = number | string;

interface Props {
    programme: {
        id: string;
        name: string;
        summary: string | null;
        funder: string;
        starts: string;
        ends: string;
        ages: string;
        hubs: number;
    };
    progress: Record<string, { value: Cell; target: number; percent: number }>;
    trend: ({ period: string; provisional: boolean } & Record<string, Cell | boolean>)[];
    indicator: string;
    breakdown: Record<'hub' | 'city' | 'province' | 'tier' | 'age', Record<string, Cell>>;
    units: Record<string, string>;
    invoices: { number: string; period: string; amount: string; status: string }[];
    stories: { title: string; body: string; hub: string }[];
    canRequest: boolean;
    indicators: string[];
    minCell: number;
}

export default function ProgrammePage({
    programme,
    progress,
    trend,
    indicator,
    breakdown,
    units,
    invoices,
    stories,
    canRequest,
    indicators,
    minCell,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const change = useForm({ indicator: Object.keys(progress)[0] ?? indicators[0] ?? '', target: '', reason: '' });
    const show = (key: string, v: Cell) =>
        typeof v === 'string'
            ? t('funder.fewer_than', { n: minCell })
            : units[key] === 'cents'
              ? formatMoney(v, { wholeRands: true })
              : v.toLocaleString('en-ZA');

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={programme.name} />
            <Link href="/funder" className="text-primary text-sm font-semibold hover:underline">
                ← {t('funder.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{programme.name}</h1>
                    <p className="text-fg-muted">
                        {programme.funder} · {programme.starts} - {programme.ends} ·{' '}
                        {t('funder.ages', { ages: programme.ages })} · {t('funder.hubs', { count: programme.hubs })}
                    </p>
                </div>
                <span className="flex gap-2">
                    <a
                        href={`/funder/programmes/${programme.id}/report`}
                        className={buttonVariants({ variant: 'secondary' })}
                    >
                        {t('funder.report')}
                    </a>
                    <a
                        href={`/funder/programmes/${programme.id}/export.csv`}
                        className={buttonVariants({ variant: 'ghost' })}
                    >
                        CSV
                    </a>
                </span>
            </div>
            <div className="mt-3">
                <Alert tone="info" title={t('funder.privacy', { n: minCell })} />
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.target && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.target} />
                </div>
            )}
            <ul className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {Object.entries(progress).map(([key, p]) => (
                    <li key={key}>
                        <Card className="h-full">
                            <p className="text-fg font-semibold">{t(`insights.indicator.${key}`)}</p>
                            <p className="text-fg mt-1 text-2xl font-bold">{show(key, p.value)}</p>
                            <p className="text-fg-muted text-sm">
                                {t('funder.of_target', { target: show(key, p.target) })}
                            </p>
                            {typeof p.value === 'number' && (
                                <div className="mt-2">
                                    <ProgressBar value={Math.min(100, p.percent)} label={`${p.percent}%`} />
                                </div>
                            )}
                            <details className="mt-2 text-sm">
                                <summary className="text-primary cursor-pointer">{t('funder.definition')}</summary>
                                <p className="text-fg-muted mt-1">{t(`insights.indicator.${key}.definition`)}</p>
                            </details>
                        </Card>
                    </li>
                ))}
            </ul>
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('funder.trend')}</CardTitle>
                    <div className="mt-2 overflow-x-auto" tabIndex={0} role="region" aria-label={t('funder.trend')}>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-fg-muted text-left">
                                    <th className="py-1">{t('funder.month')}</th>
                                    {Object.keys(progress).map((k) => (
                                        <th key={k} className="text-right">
                                            {t(`insights.indicator.${k}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {trend.map((row) => (
                                    <tr key={row.period} className="border-line border-t">
                                        <td className="text-fg py-1">
                                            {row.period}{' '}
                                            {row.provisional && <Badge tone="warning">{t('funder.provisional')}</Badge>}
                                        </td>
                                        {Object.keys(progress).map((k) => (
                                            <td key={k} className="text-fg text-right">
                                                {show(k, (row[k] as Cell | undefined) ?? 0)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
                <Card>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle>{t('funder.breakdown')}</CardTitle>
                        <Select
                            aria-label={t('funder.indicator')}
                            value={indicator}
                            onChange={(e) =>
                                router.get(
                                    `/funder/programmes/${programme.id}`,
                                    { indicator: e.target.value },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {Object.keys(progress).map((k) => (
                                <option key={k} value={k}>
                                    {t(`insights.indicator.${k}`)}
                                </option>
                            ))}
                        </Select>
                    </div>
                    {(['province', 'city', 'hub', 'tier', 'age'] as const).map((dim) =>
                        Object.keys(breakdown[dim]).length === 0 ? null : (
                            <div key={dim} className="mt-3">
                                <p className="text-fg text-sm font-semibold">{t(`funder.by.${dim}`)}</p>
                                <ul className="text-sm">
                                    {Object.entries(breakdown[dim]).map(([name, v]) => (
                                        <li key={name} className="flex justify-between gap-2">
                                            <span className="text-fg">
                                                {dim === 'tier' ? t(`commercial.tier.${name}`) : name}
                                            </span>
                                            <span className="text-fg">{show(indicator, v)}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ),
                    )}
                    <p className="text-fg-muted mt-3 text-xs">{t('funder.sex_note')}</p>
                </Card>
                {stories.length > 0 && (
                    <Card>
                        <CardTitle>{t('funder.stories')}</CardTitle>
                        {stories.map((s) => (
                            <div key={s.title} className="mt-3">
                                <p className="text-fg font-semibold">{s.title}</p>
                                <p className="text-fg text-sm">{s.body}</p>
                                <p className="text-fg-muted text-xs">{s.hub}</p>
                            </div>
                        ))}
                    </Card>
                )}
                <Card>
                    <CardTitle>{t('funder.invoices')}</CardTitle>
                    <ul className="mt-2 text-sm">
                        {invoices.map((i) => (
                            <li key={i.number} className="flex justify-between gap-2">
                                <span className="text-fg">
                                    {i.number} · {i.period}
                                </span>
                                <span>
                                    {i.amount} <Badge>{t(`commercial.invoice.status_${i.status}`)}</Badge>
                                </span>
                            </li>
                        ))}
                        {invoices.length === 0 && <li className="text-fg-muted">-</li>}
                    </ul>
                    {canRequest && (
                        <form
                            className="border-line mt-4 grid gap-2 border-t pt-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                change.transform((d) => ({ ...d, target: Number(d.target) }));
                                change.post(`/funder/programmes/${programme.id}/targets`, { preserveScroll: true });
                            }}
                        >
                            <p className="text-fg text-sm font-semibold">{t('funder.change_target')}</p>
                            <Field label={t('funder.indicator')}>
                                <Select
                                    value={change.data.indicator}
                                    onChange={(e) => change.setData('indicator', e.target.value)}
                                >
                                    {indicators.map((k) => (
                                        <option key={k} value={k}>
                                            {t(`insights.indicator.${k}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('funder.new_target')}>
                                <Input
                                    type="number"
                                    min={0}
                                    value={change.data.target}
                                    onChange={(e) => change.setData('target', e.target.value)}
                                />
                            </Field>
                            <Field label={t('funder.reason')} error={change.errors.reason}>
                                <Input
                                    value={change.data.reason}
                                    onChange={(e) => change.setData('reason', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    disabled={!change.data.target || change.data.reason.length < 5}
                                >
                                    {t('commercial.console.send_for_approval')}
                                </Button>
                            </div>
                        </form>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
