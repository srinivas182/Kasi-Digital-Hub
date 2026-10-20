import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    dispute: {
        id: string;
        reason: string;
        details: string;
        status: string;
        response: string | null;
        respondBy: string;
        opened: string;
        outcome: string | null;
        outcomeNote: string | null;
    };
    order: {
        number: string;
        shop: string;
        shopSlug: string;
        total_cents: number;
        payment: string;
        paymentStatus: string;
        refunded_cents: number;
        status: string;
        items: string[];
    };
    files: { url: string; by: string }[];
    canDecide: boolean;
}

/** KasiHub support decides a KasiMarket dispute (S27). */
export default function DisputePage({ dispute, order, files, canDecide }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const paidOnline = ['paid', 'part_refunded'].includes(order.paymentStatus);
    const form = useForm({ outcome: paidOnline ? 'refund_full' : 'mediated', amount: '', note: '' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.dispute.inbox_title', { number: order.number })} />
            <h1 className="text-fg text-2xl font-bold">{t('market.dispute.inbox_title', { number: order.number })}</h1>
            <p className="text-fg-muted">
                {order.shop} · {formatMoney(order.total_cents)} ·{' '}
                {order.payment === 'online' ? t(`market.pay.status.${order.paymentStatus}`) : t('market.pay.cash')} ·{' '}
                {t('market.dispute.opened_at', { date: formatDateTime(dispute.opened) })}
            </p>
            <p className="mt-1 text-sm">
                <a href={`/market/moderate/${order.shopSlug}`} className="text-primary font-semibold hover:underline">
                    {t('market.dispute.shop_record')}
                </a>
            </p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.trust && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.trust} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('market.dispute.buyer_says')}</CardTitle>
                    <p className="text-fg mt-1 text-sm">
                        <Badge tone="warning">{t(`market.dispute.reason.${dispute.reason}`)}</Badge>
                    </p>
                    <p className="text-fg mt-2 text-sm whitespace-pre-line">{dispute.details}</p>
                    <p className="text-fg-muted mt-2 text-sm">{order.items.join(', ')}</p>
                    <CardTitle className="mt-4">{t('market.dispute.trader_said')}</CardTitle>
                    <p className="text-fg mt-1 text-sm">
                        {dispute.response ??
                            t('market.dispute.no_response', { date: formatDateTime(dispute.respondBy) })}
                    </p>
                    <CardTitle className="mt-4">{t('market.dispute.evidence')}</CardTitle>
                    <ul className="mt-1 text-sm">
                        {files.map((f, n) => (
                            <li key={n}>
                                <a href={f.url} className="text-primary hover:underline" target="_blank" rel="noopener">
                                    {t('market.dispute.photo_from', {
                                        n: n + 1,
                                        who: t(`market.dispute.party.${f.by}`),
                                    })}
                                </a>
                            </li>
                        ))}
                        {files.length === 0 && <li className="text-fg-muted">{t('market.dispute.no_photos')}</li>}
                    </ul>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('market.dispute.decision')}</CardTitle>
                    {canDecide ? (
                        <form
                            className="mt-2 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.transform((d) => ({
                                    outcome: d.outcome,
                                    cents: d.outcome === 'refund_part' ? Math.round(Number(d.amount) * 100) : null,
                                    note: d.note,
                                }));
                                form.post(`/market/disputes/${dispute.id}/decide`, { preserveScroll: true });
                            }}
                        >
                            <Field label={t('market.dispute.outcome_label')}>
                                <Select
                                    value={form.data.outcome}
                                    onChange={(e) => form.setData('outcome', e.target.value)}
                                >
                                    {(paidOnline
                                        ? ['refund_full', 'refund_part', 'no_refund']
                                        : ['mediated', 'no_refund']
                                    ).map((o) => (
                                        <option key={o} value={o}>
                                            {t(`market.dispute.outcome.${o}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {!paidOnline && (
                                <p className="text-fg-muted text-xs">{t('market.dispute.cash_mediation')}</p>
                            )}
                            {form.data.outcome === 'refund_part' && (
                                <Field label={t('market.refund.amount')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                    />
                                </Field>
                            )}
                            <Field label={t('market.dispute.note_label')}>
                                <Textarea
                                    rows={3}
                                    value={form.data.note}
                                    onChange={(e) => form.setData('note', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    disabled={form.data.note.trim().length < 5}
                                    loading={form.processing}
                                >
                                    {t('market.dispute.decide')}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <p className="text-fg mt-2 text-sm">
                            {t(`market.dispute.outcome.${dispute.outcome ?? 'settled'}`)}
                            {dispute.outcomeNote ? ` - ${dispute.outcomeNote}` : ''}
                        </p>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
