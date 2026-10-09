import { useForm } from '@inertiajs/react';
import { useState } from 'react';

import { Badge, Card, CardTitle, StatCard } from '@/components/ui/display';
import { Button } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Field, Input } from '@/components/ui/form';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage } from '../components/AdminPage';
import { BarChart } from '../components/BarChart';
import { ReasonDialog } from '../components/ReasonDialog';

interface Feature {
    feature: string;
    label: string;
    enabled: boolean;
    budgetCents: number | null;
    calls: number;
    ok: number;
    failed: number;
    refused: number;
    costCents: number;
}

interface Recent {
    id: string;
    feature: string;
    version: number;
    model: string;
    outcome: string;
    tokens: number;
    costCents: number;
    ms: number;
    at: string;
    input: string | null;
    output: string | null;
}

interface Props {
    driver: string;
    allEnabled: boolean;
    budgets: { per_person_day_calls: number; per_hub_month_cents: number; platform_month_cents: number };
    monthCost: number;
    features: Feature[];
    daily: { day: string; calls: number; costCents: number }[];
    recent: Recent[];
    canManage: boolean;
}

function Switcher({
    feature,
    enabled,
    budgetCents,
    label,
}: {
    feature: string;
    enabled: boolean;
    budgetCents: number | null;
    label: string;
}) {
    const { t } = useTranslation();
    return (
        <ReasonDialog
            trigger={
                <Button size="sm" variant={enabled ? 'danger' : 'primary'}>
                    {enabled ? t('admin.ai.switch_off') : t('admin.ai.switch_on')}
                </Button>
            }
            title={`${enabled ? t('admin.ai.switch_off') : t('admin.ai.switch_on')}: ${label}`}
            confirmLabel={enabled ? t('admin.ai.switch_off') : t('admin.ai.switch_on')}
            action="/admin/ai"
            method="put"
            extra={{ feature, enabled: !enabled, monthly_budget_cents: budgetCents }}
            danger={enabled}
        />
    );
}

function BudgetForm({
    feature,
    enabled,
    budgetCents,
}: {
    feature: string;
    enabled: boolean;
    budgetCents: number | null;
}) {
    const { t } = useTranslation();
    const form = useForm({
        feature,
        enabled,
        monthly_budget_cents: budgetCents === null ? '' : String(budgetCents / 100),
        reason: '',
    });
    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({
                    ...d,
                    monthly_budget_cents:
                        d.monthly_budget_cents === '' ? null : Math.round(Number(d.monthly_budget_cents) * 100),
                }));
                form.put('/admin/ai', { preserveScroll: true });
            }}
        >
            <Field label={t('admin.ai.budget')} error={form.errors.monthly_budget_cents}>
                <Input
                    type="number"
                    min={0}
                    className="w-28"
                    value={form.data.monthly_budget_cents}
                    onChange={(e) => form.setData('monthly_budget_cents', e.target.value)}
                />
            </Field>
            <Field label={t('admin.reason')} error={form.errors.reason}>
                <Input value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
            </Field>
            <Button type="submit" size="sm" variant="secondary" disabled={form.data.reason.trim().length < 5}>
                {t('admin.ai.set_budget')}
            </Button>
        </form>
    );
}

export default function Ai({ driver, allEnabled, budgets, monthCost, features, daily, recent, canManage }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState<string | null>(null);

    return (
        <AdminPage title={t('admin.ai.title')} crumbs={[{ label: t('admin.ai.title') }]}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label={t('admin.ai.month_cost')} value={formatMoney(monthCost)} emphasis />
                <StatCard label={t('admin.ai.platform_cap')} value={formatMoney(budgets.platform_month_cents)} />
                <StatCard label={t('admin.ai.hub_cap')} value={formatMoney(budgets.per_hub_month_cents)} />
                <StatCard label={t('admin.ai.person_cap')} value={String(budgets.per_person_day_calls)} />
            </div>
            <Card className="mt-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle>
                        {t('admin.ai.all')}{' '}
                        <Badge tone={allEnabled ? 'success' : 'danger'}>{allEnabled ? 'On' : 'Off'}</Badge>
                    </CardTitle>
                    <span className="text-fg-muted text-sm">
                        {t('admin.ai.driver')}: {driver}
                    </span>
                    {canManage && (
                        <Switcher feature="*" enabled={allEnabled} budgetCents={null} label={t('admin.ai.all')} />
                    )}
                </div>
            </Card>
            <div className="mt-6">
                <DataTable<Feature>
                    caption={t('admin.ai.title')}
                    rows={features}
                    rowKey={(f) => f.feature}
                    columns={[
                        {
                            key: 'label',
                            header: 'Feature',
                            cell: (f) => (
                                <span>
                                    <span className="font-semibold">{f.label}</span>{' '}
                                    {!f.enabled && <Badge tone="danger">Off</Badge>}
                                    <span className="text-fg-muted block text-xs">
                                        {f.budgetCents === null
                                            ? t('admin.ai.no_budget')
                                            : `${t('admin.ai.budget')}: ${formatMoney(f.budgetCents)}`}
                                    </span>
                                </span>
                            ),
                        },
                        { key: 'calls', header: t('admin.ai.calls'), cell: (f) => f.calls },
                        { key: 'ok', header: t('admin.ai.ok'), cell: (f) => f.ok, hideOnMobile: true },
                        { key: 'failed', header: t('admin.ai.failed'), cell: (f) => f.failed, hideOnMobile: true },
                        { key: 'refused', header: t('admin.ai.refused'), cell: (f) => f.refused, hideOnMobile: true },
                        { key: 'cost', header: t('admin.ai.cost'), cell: (f) => formatMoney(f.costCents) },
                        {
                            key: 'actions',
                            header: '',
                            cell: (f) =>
                                canManage ? (
                                    <div className="flex flex-col gap-2">
                                        <Switcher
                                            feature={f.feature}
                                            enabled={f.enabled}
                                            budgetCents={f.budgetCents}
                                            label={f.label}
                                        />
                                        <details>
                                            <summary className="text-primary cursor-pointer text-sm font-semibold">
                                                {t('admin.ai.set_budget')}
                                            </summary>
                                            <div className="mt-2">
                                                <BudgetForm
                                                    feature={f.feature}
                                                    enabled={f.enabled}
                                                    budgetCents={f.budgetCents}
                                                />
                                            </div>
                                        </details>
                                    </div>
                                ) : null,
                        },
                    ]}
                />
            </div>
            <Card className="mt-6">
                <CardTitle>{t('admin.ai.daily')}</CardTitle>
                <div className="mt-4">
                    <BarChart
                        label={t('admin.ai.daily')}
                        data={daily.map((d) => ({ label: d.day.slice(5), value: d.calls }))}
                    />
                </div>
            </Card>
            <Card className="mt-6">
                <CardTitle>{t('admin.ai.recent')}</CardTitle>
                <ul className="mt-3 flex flex-col gap-2 text-sm">
                    {recent.map((r) => (
                        <li key={r.id} className="border-line border-b pb-2 last:border-0">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-mono text-xs">
                                    {r.feature} v{r.version} · {r.model}
                                </span>
                                <span className="flex items-center gap-2">
                                    <Badge tone={r.outcome === 'ok' ? 'success' : 'warning'}>
                                        {t(`admin.ai.outcome.${r.outcome}`)}
                                    </Badge>
                                    <span className="text-fg-muted">
                                        {r.tokens} tokens · {formatMoney(r.costCents)} · {r.ms} ms ·{' '}
                                        {formatDateTime(r.at)}
                                    </span>
                                    {(r.input || r.output) && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setOpen(open === r.id ? null : r.id)}
                                        >
                                            {t('admin.ai.view_text')}
                                        </Button>
                                    )}
                                </span>
                            </div>
                            {open === r.id && (
                                <div className="mt-2 grid gap-2 md:grid-cols-2">
                                    <pre className="bg-surface-muted overflow-x-auto rounded p-2 text-xs whitespace-pre-wrap">
                                        <strong>{t('admin.ai.input')}</strong>
                                        {'\n'}
                                        {r.input}
                                    </pre>
                                    <pre className="bg-surface-muted overflow-x-auto rounded p-2 text-xs whitespace-pre-wrap">
                                        <strong>{t('admin.ai.output')}</strong>
                                        {'\n'}
                                        {r.output}
                                    </pre>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            </Card>
        </AdminPage>
    );
}
