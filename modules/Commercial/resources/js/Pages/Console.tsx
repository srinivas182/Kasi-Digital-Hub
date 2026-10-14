import { Head, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

type Band = { price: string; floor: string; ceiling: string } | null;

interface Props {
    can: { manage: boolean; finance: boolean };
    byStream: Record<string, { sales: string; kasi: string }>;
    league: { hub: string; total: string; orders: number }[];
    approvals: { id: number; kind: string; change: Record<string, unknown>; by: string; mine: boolean }[];
    overdue: { id: string; number: string; hub: string; amount: string; due: string }[];
    issues: {
        id: number;
        settlement_date: string;
        provider_ref: string | null;
        issue: string;
        ours_cents: number | null;
        theirs_cents: number | null;
    }[];
    hubs: {
        id: string;
        name: string;
        package: string;
        company: boolean;
        bank: boolean;
        franchise: boolean;
        blocked: boolean;
    }[];
    catalogue: { id: number; key: string; stream: string; name: string; merchant: string; metro: Band; rural: Band }[];
    rules: Record<string, { kasi_bp: number; hub_bp: number; partner_bp: number } | null>;
    recent: {
        id: string;
        number: string;
        product: string;
        total: string;
        refundable: number;
        status: string;
        channel: string;
    }[];
}

export default function Console({
    can,
    byStream,
    league,
    approvals,
    overdue,
    issues,
    hubs,
    catalogue,
    rules,
    recent,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const price = useForm({
        product: String(catalogue[0]?.id ?? ''),
        tier: 'metro',
        price: '',
        floor: '',
        ceiling: '',
        effective_from: new Date().toISOString().slice(0, 10),
    });
    const rule = useForm({
        stream: 'work',
        kasi: '',
        hub: '',
        partner: '0',
        effective_from: new Date().toISOString().slice(0, 10),
        note: '',
    });
    const rand = (v: string) => Math.round(Number(v) * 100);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('commercial.console.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('commercial.console.title')}</h1>
                {can.finance && (
                    <a href="/commercial/ledger.csv" className={buttonVariants({ variant: 'secondary' })}>
                        {t('commercial.console.ledger')}
                    </a>
                )}
            </div>
            <div className="mt-3">
                <Alert tone="warning" title={t('commercial.console.placeholders')} />
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.commercial && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.commercial} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('commercial.console.this_month')}</CardTitle>
                    <table className="mt-2 w-full text-sm">
                        <thead>
                            <tr className="text-fg-muted text-left">
                                <th className="py-1">{t('commercial.console.stream')}</th>
                                <th className="text-right">{t('commercial.console.sales')}</th>
                                <th className="text-right">{t('commercial.console.kasi')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(byStream).map(([s, v]) => (
                                <tr key={s} className="border-line border-t">
                                    <td className="text-fg py-1">{t(`commercial.stream.${s}`)}</td>
                                    <td className="text-fg text-right">{v.sales}</td>
                                    <td className="text-fg text-right">{v.kasi}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <CardTitle className="mt-4">{t('commercial.console.league')}</CardTitle>
                    <ol className="mt-2 list-decimal pl-5 text-sm">
                        {league.map((l) => (
                            <li key={l.hub} className="text-fg">
                                {l.hub} - {l.total} ({l.orders})
                            </li>
                        ))}
                    </ol>
                </Card>
                <Card>
                    <CardTitle>{t('commercial.console.approvals')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2 text-sm">
                        {approvals.map((a) => (
                            <li key={a.id} className="border-line border-b pb-2">
                                <p className="text-fg">
                                    <Badge>{t(`commercial.approval.kind_${a.kind}`)}</Badge> {JSON.stringify(a.change)}{' '}
                                    · {t('commercial.approval.by', { name: a.by })}
                                </p>
                                {a.mine ? (
                                    <p className="text-fg-muted">{t('commercial.approval.not_own')}</p>
                                ) : (
                                    <div className="mt-1 flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    `/commercial/approvals/${a.id}`,
                                                    { approve: true },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('connect.coordinator.approve')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    `/commercial/approvals/${a.id}`,
                                                    { approve: false },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('learn.review.reject')}
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                        {approvals.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
                    </ul>
                </Card>
                <Card className="lg:col-span-2">
                    <CardTitle>{t('commercial.console.catalogue')}</CardTitle>
                    <div className="mt-2 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-fg-muted text-left">
                                    <th className="py-1">{t('commercial.console.product')}</th>
                                    <th>{t('commercial.tier.metro')}</th>
                                    <th>{t('commercial.tier.rural')}</th>
                                    <th>{t('commercial.console.split')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {catalogue.map((p) => {
                                    const r = rules[p.stream];
                                    return (
                                        <tr key={p.id} className="border-line border-t">
                                            <td className="text-fg py-1">{p.name}</td>
                                            <td className="text-fg">
                                                {p.metro
                                                    ? `${p.metro.price} (${p.metro.floor}-${p.metro.ceiling})`
                                                    : '-'}
                                            </td>
                                            <td className="text-fg">
                                                {p.rural
                                                    ? `${p.rural.price} (${p.rural.floor}-${p.rural.ceiling})`
                                                    : '-'}
                                            </td>
                                            <td className="text-fg-muted">
                                                {r
                                                    ? `Kasi ${r.kasi_bp / 100}% · hub ${r.hub_bp / 100}% · partner ${r.partner_bp / 100}%`
                                                    : '-'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {can.manage && (
                        <div className="mt-4 grid gap-6 md:grid-cols-2">
                            <form
                                className="grid grid-cols-2 gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    price.transform((d) => ({
                                        ...d,
                                        product: Number(d.product),
                                        price: rand(d.price),
                                        floor: rand(d.floor),
                                        ceiling: rand(d.ceiling),
                                    }));
                                    price.post('/commercial/prices', { preserveScroll: true });
                                }}
                            >
                                <p className="text-fg col-span-2 text-sm font-semibold">
                                    {t('commercial.console.propose_price')}
                                </p>
                                <Field label={t('commercial.console.product')} className="col-span-2">
                                    <Select
                                        value={price.data.product}
                                        onChange={(e) => price.setData('product', e.target.value)}
                                    >
                                        {catalogue.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('commercial.console.tier')}>
                                    <Select
                                        value={price.data.tier}
                                        onChange={(e) => price.setData('tier', e.target.value)}
                                    >
                                        <option value="metro">{t('commercial.tier.metro')}</option>
                                        <option value="rural">{t('commercial.tier.rural')}</option>
                                    </Select>
                                </Field>
                                <Field label={t('commercial.console.price_r')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={price.data.price}
                                        onChange={(e) => price.setData('price', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('commercial.console.floor_r')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={price.data.floor}
                                        onChange={(e) => price.setData('floor', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('commercial.console.ceiling_r')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={price.data.ceiling}
                                        onChange={(e) => price.setData('ceiling', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('commercial.console.from')} className="col-span-2">
                                    <Input
                                        type="date"
                                        value={price.data.effective_from}
                                        onChange={(e) => price.setData('effective_from', e.target.value)}
                                    />
                                </Field>
                                <div className="col-span-2">
                                    <Button type="submit" size="sm" variant="secondary">
                                        {t('commercial.console.send_for_approval')}
                                    </Button>
                                </div>
                            </form>
                            <form
                                className="grid grid-cols-3 gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    rule.transform((d) => ({
                                        ...d,
                                        kasi: Number(d.kasi),
                                        hub: Number(d.hub),
                                        partner: Number(d.partner),
                                    }));
                                    rule.post('/commercial/rules', { preserveScroll: true });
                                }}
                            >
                                <p className="text-fg col-span-3 text-sm font-semibold">
                                    {t('commercial.console.propose_rule')}
                                </p>
                                <Field label={t('commercial.console.stream')} className="col-span-3">
                                    <Select
                                        value={rule.data.stream}
                                        onChange={(e) => rule.setData('stream', e.target.value)}
                                    >
                                        {Object.keys(rules).map((s) => (
                                            <option key={s} value={s}>
                                                {t(`commercial.stream.${s}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label="Kasi %">
                                    <Input
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={rule.data.kasi}
                                        onChange={(e) => rule.setData('kasi', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('commercial.console.hub_pct')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={rule.data.hub}
                                        onChange={(e) => rule.setData('hub', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('commercial.console.partner_pct')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={rule.data.partner}
                                        onChange={(e) => rule.setData('partner', e.target.value)}
                                    />
                                </Field>
                                <div className="col-span-3">
                                    <Button type="submit" size="sm" variant="secondary">
                                        {t('commercial.console.send_for_approval')}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}
                </Card>
                <Card>
                    <CardTitle>{t('commercial.console.hubs')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {hubs.map((h) => (
                            <li key={h.id} className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-fg">{h.name}</span>
                                <span className="flex gap-1">
                                    {h.blocked && <Badge tone="danger">{t('commercial.console.blocked')}</Badge>}
                                    <Badge tone={h.company && h.bank && h.franchise ? 'success' : 'warning'}>
                                        {h.company && h.bank && h.franchise
                                            ? t('commercial.console.ready')
                                            : t('commercial.console.not_ready')}
                                    </Badge>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card>
                    <CardTitle>{t('commercial.console.overdue')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {overdue.map((i) => (
                            <li key={i.id} className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-fg">
                                    {i.number} · {i.hub} · {i.amount}
                                </span>
                                {can.finance && (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            router.post(
                                                `/commercial/invoices/${i.id}/paid`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('commercial.console.mark_paid')}
                                    </Button>
                                )}
                            </li>
                        ))}
                        {overdue.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
                    </ul>
                    <CardTitle className="mt-4">{t('commercial.console.issues')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {issues.map((i) => (
                            <li key={i.id} className="text-fg">
                                {i.settlement_date} · {t(`commercial.issue.${i.issue}`)} · {i.provider_ref}
                            </li>
                        ))}
                        {issues.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
                    </ul>
                </Card>
                <Card className="lg:col-span-2">
                    <CardTitle>{t('commercial.console.recent')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {recent.map((o) => (
                            <li
                                key={o.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-1"
                            >
                                <span className="text-fg">
                                    {o.number} · {o.product} · {o.total}{' '}
                                    <Badge tone="neutral">{t(`commercial.channel.${o.channel}`)}</Badge>{' '}
                                    <Badge>{t(`commercial.order.status.${o.status}`)}</Badge>
                                </span>
                                {can.finance && o.refundable > 0 && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            const reason = window.prompt(t('commercial.refund.reason'));
                                            if (reason)
                                                router.post(
                                                    `/commercial/orders/${o.id}/refund`,
                                                    { amount: o.refundable, reason },
                                                    { preserveScroll: true },
                                                );
                                        }}
                                    >
                                        {t('commercial.refund.full')}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}
