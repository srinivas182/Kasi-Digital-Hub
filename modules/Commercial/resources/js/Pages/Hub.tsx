import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    hub: { id: string; name: string; package: string; tier: string };
    isOwner: boolean;
    period: string;
    checklist: Record<string, boolean>;
    canSell: boolean;
    details: {
        company_registration: string;
        vat_registered: boolean;
        vat_number: string;
        bank: string | null;
        franchiseVersion: string;
        pendingBank: boolean;
    };
    statement: Record<string, string>;
    invoices: { number: string; period: string; amount: string; status: string; due: string }[];
    payouts: { period: string; amount: string; status: string }[];
    sales: {
        id: string;
        number: string;
        product: string;
        channel: string;
        total: string;
        hubShare: string;
        status: string;
        at: string | null;
    }[];
    posProducts: { key: string; name: string; unit: string; price: number; floor: number; ceiling: number }[];
}

export default function HubFinancePage({
    hub,
    isOwner,
    period,
    checklist,
    canSell,
    details,
    statement,
    invoices,
    payouts,
    sales,
    posProducts,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const info = useForm({
        company_registration: details.company_registration,
        vat_registered: details.vat_registered,
        vat_number: details.vat_number,
        hub: hub.id,
    });
    const bank = useForm<{ letter: File | null; label: string; hub: string }>({ letter: null, label: '', hub: hub.id });
    const sale = useForm({ product: posProducts[0]?.key ?? '', quantity: '1', buyer: '', hub: hub.id });
    const chosen = posProducts.find((p) => p.key === sale.data.product);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('commercial.hub.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">
                    {t('commercial.hub.title')} - {hub.name}
                </h1>
                <span className="flex gap-1">
                    <Badge>{t(`commercial.package.${hub.package}`)}</Badge>
                    <Badge tone="neutral">{t(`commercial.tier.${hub.tier}`)}</Badge>
                </span>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.sale && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.sale} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('commercial.pos.title')}</CardTitle>
                        {canSell ? (
                            <form
                                className="mt-3 grid gap-3 sm:grid-cols-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    sale.transform((d) => ({
                                        ...d,
                                        quantity: Number(d.quantity),
                                        buyer: d.buyer || null,
                                    }));
                                    sale.post('/commercial/hub/sell', {
                                        preserveScroll: true,
                                        onSuccess: () => sale.reset('quantity', 'buyer'),
                                    });
                                }}
                            >
                                <Field label={t('commercial.pos.product')} className="sm:col-span-3">
                                    <Select
                                        value={sale.data.product}
                                        onChange={(e) => sale.setData('product', e.target.value)}
                                    >
                                        {posProducts.map((p) => (
                                            <option key={p.key} value={p.key}>
                                                {p.name} - {formatMoney(p.price)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('commercial.checkout.quantity')}>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={sale.data.quantity}
                                        onChange={(e) => sale.setData('quantity', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('commercial.pos.buyer')} className="sm:col-span-2">
                                    <Input
                                        value={sale.data.buyer}
                                        onChange={(e) => sale.setData('buyer', e.target.value)}
                                    />
                                </Field>
                                <p className="text-fg font-semibold sm:col-span-2">
                                    {chosen &&
                                        t('commercial.checkout.total', {
                                            total: formatMoney(chosen.price * (Number(sale.data.quantity) || 1)),
                                        })}
                                </p>
                                <Button type="submit" loading={sale.processing}>
                                    {t('commercial.pos.sell')}
                                </Button>
                            </form>
                        ) : (
                            <p className="text-fg-muted mt-2 text-sm">{t('commercial.hub.not_ready')}</p>
                        )}
                    </Card>
                    <Card>
                        <CardTitle>{t('commercial.hub.statement', { period })}</CardTitle>
                        <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            {Object.entries(statement).map(([k, v]) => (
                                <div key={k} className="contents">
                                    <dt className="text-fg-muted">{t(`commercial.statement.${k}`)}</dt>
                                    <dd className="text-fg text-right font-medium">{v}</dd>
                                </div>
                            ))}
                        </dl>
                        <p className="text-fg-muted mt-2 text-xs">{t('commercial.hub.statement_hint')}</p>
                    </Card>
                    <Card>
                        <CardTitle>{t('commercial.hub.sales')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-1 text-sm">
                            {sales.map((s) => (
                                <li
                                    key={s.id}
                                    className="border-line flex flex-wrap justify-between gap-2 border-b pb-1"
                                >
                                    <span className="text-fg">
                                        {s.number} · {s.product}{' '}
                                        <Badge tone="neutral">{t(`commercial.channel.${s.channel}`)}</Badge>
                                    </span>
                                    <span className="text-fg-muted">
                                        {s.total} ({t('commercial.hub.your_share', { amount: s.hubShare })}) ·{' '}
                                        {s.at ? formatDateTime(s.at) : ''}
                                    </span>
                                </li>
                            ))}
                            {sales.length === 0 && <li className="text-fg-muted">{t('commercial.hub.no_sales')}</li>}
                        </ul>
                    </Card>
                </div>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('commercial.hub.ready')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-1 text-sm">
                            {Object.entries(checklist).map(([k, ok]) => (
                                <li key={k} className="text-fg flex items-center gap-2">
                                    {ok ? (
                                        <CheckCircle2
                                            className="text-success-text size-4"
                                            aria-label={t('partner.elig.met')}
                                        />
                                    ) : (
                                        <Circle className="size-4" aria-label={t('partner.elig.not_met')} />
                                    )}
                                    {t(`commercial.check.${k}`)}
                                </li>
                            ))}
                        </ul>
                    </Card>
                    {isOwner && (
                        <Card>
                            <CardTitle>{t('commercial.hub.details')}</CardTitle>
                            <form
                                className="mt-3 flex flex-col gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    info.post('/commercial/hub/details', { preserveScroll: true });
                                }}
                            >
                                <Field label={t('work.employer.cipc')} error={info.errors.company_registration}>
                                    <Input
                                        value={info.data.company_registration}
                                        onChange={(e) => info.setData('company_registration', e.target.value)}
                                    />
                                </Field>
                                <Checkbox
                                    label={t('commercial.hub.vat_registered')}
                                    checked={info.data.vat_registered}
                                    onCheckedChange={(c) => info.setData('vat_registered', c === true)}
                                />
                                {info.data.vat_registered && (
                                    <Field label={t('commercial.hub.vat_number')} error={info.errors.vat_number}>
                                        <Input
                                            value={info.data.vat_number}
                                            onChange={(e) => info.setData('vat_number', e.target.value)}
                                        />
                                    </Field>
                                )}
                                <div>
                                    <Button type="submit" size="sm" variant="secondary">
                                        {t('work.save')}
                                    </Button>
                                </div>
                            </form>
                            <div className="border-line mt-4 border-t pt-3">
                                <p className="text-fg text-sm font-semibold">{t('commercial.hub.bank')}</p>
                                <p className="text-fg-muted text-sm">{details.bank ?? t('commercial.hub.no_bank')}</p>
                                {details.pendingBank ? (
                                    <p className="text-warning-text text-sm">{t('commercial.hub.bank_pending')}</p>
                                ) : (
                                    <form
                                        className="mt-2 flex flex-col gap-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            bank.post('/commercial/hub/bank', {
                                                forceFormData: true,
                                                preserveScroll: true,
                                            });
                                        }}
                                    >
                                        <Field
                                            label={t('commercial.hub.bank_label')}
                                            hint={t('commercial.hub.bank_label_hint')}
                                            error={bank.errors.label}
                                        >
                                            <Input
                                                value={bank.data.label}
                                                onChange={(e) => bank.setData('label', e.target.value)}
                                            />
                                        </Field>
                                        <Field label={t('commercial.hub.bank_letter')} error={bank.errors.letter}>
                                            <FileInput
                                                file={bank.data.letter}
                                                onChange={(f) => bank.setData('letter', f)}
                                                chooseLabel={t('documents.file')}
                                                photoLabel={t('documents.take_photo')}
                                            />
                                        </Field>
                                        <div>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="secondary"
                                                disabled={!bank.data.letter || !bank.data.label}
                                            >
                                                {t('commercial.hub.bank_submit')}
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </div>
                            {!checklist.franchise && (
                                <div className="border-line mt-4 border-t pt-3">
                                    <p className="text-fg text-sm">
                                        {t('commercial.hub.franchise_text', { version: details.franchiseVersion })}
                                    </p>
                                    <Button
                                        size="sm"
                                        className="mt-2"
                                        onClick={() =>
                                            router.post(
                                                '/commercial/hub/franchise',
                                                { accept: true, hub: hub.id },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('commercial.hub.franchise_accept')}
                                    </Button>
                                </div>
                            )}
                        </Card>
                    )}
                    <Card>
                        <CardTitle>{t('commercial.hub.invoices')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-1 text-sm">
                            {invoices.map((i) => (
                                <li key={i.number} className="flex justify-between gap-2">
                                    <span className="text-fg">
                                        {i.number} · {i.period}
                                    </span>
                                    <span>
                                        {i.amount}{' '}
                                        <Badge tone={i.status === 'paid' ? 'success' : 'warning'}>
                                            {t(`commercial.invoice.status_${i.status}`)}
                                        </Badge>
                                    </span>
                                </li>
                            ))}
                            {invoices.length === 0 && <li className="text-fg-muted">-</li>}
                        </ul>
                        <CardTitle className="mt-4">{t('commercial.hub.payouts')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-1 text-sm">
                            {payouts.map((p) => (
                                <li key={p.period} className="flex justify-between gap-2">
                                    <span className="text-fg">{p.period}</span>
                                    <span>
                                        {p.amount} <Badge>{t(`commercial.payout.${p.status}`)}</Badge>
                                    </span>
                                </li>
                            ))}
                            {payouts.length === 0 && <li className="text-fg-muted">-</li>}
                        </ul>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
