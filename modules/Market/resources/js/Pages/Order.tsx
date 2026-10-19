import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    order: {
        id: string;
        number: string;
        status: string;
        reason: string | null;
        eta: string | null;
        channel: string;
        fulfilment: string;
        address: string | null;
        note: string | null;
        items_cents: number;
        fee_cents: number;
        total_cents: number;
        placed: string;
        items: { title: string; option: string | null; quantity: number; unit_price_cents: number }[];
        buyerConfirmed: boolean;
    };
    shop: { name: string; slug: string; collectionNote: string | null; whatsapp: string | null };
    buyer: { name: string; phone: string | null; phoneMasked: string } | null;
    viewer: 'buyer' | 'trader';
    moves: string[];
    messages: { id: string; mine: boolean; body: string; held: boolean; at: string }[];
}

/** One order, for its buyer or its trader (S25). */
export default function OrderPage({ order, shop, buyer, viewer, moves, messages }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [reason, setReason] = useState('');
    const [eta, setEta] = useState(order.eta ?? '');
    const message = useForm({ body: '' });
    const whatsappText = encodeURIComponent(
        [
            t('market.order.whatsapp_intro', { number: order.number }),
            ...order.items.map((i) => `${i.quantity} × ${i.title}${i.option ? ` (${i.option})` : ''}`),
            t('market.order.whatsapp_total', { total: formatMoney(order.total_cents) }),
        ].join('\n'),
    );
    const move = (to: string) =>
        router.post(
            `/market/shop/orders/${order.id}/move`,
            { to, reason: reason || null, eta: eta || null },
            { preserveScroll: true },
        );
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={order.number} />
            <Link
                href={viewer === 'trader' ? '/market/shop/orders' : '/market/orders'}
                className="text-primary text-sm font-semibold hover:underline"
            >
                ← {viewer === 'trader' ? t('market.trader_orders.title') : t('market.orders.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('market.order.title', { number: order.number })}</h1>
                <Badge>{t(`market.order.status.${order.status}`)}</Badge>
            </div>
            <p className="text-fg-muted">
                {shop.name} · {formatDateTime(order.placed)} · {t(`market.order.fulfilment.${order.fulfilment}`)}
                {order.eta ? ` · ${t('market.order.eta', { eta: order.eta })}` : ''}
            </p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.order && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.order} />
                </div>
            )}
            {order.reason && (
                <div className="mt-3">
                    <Alert tone="info" title={order.reason} />
                </div>
            )}
            {viewer === 'buyer' && order.channel === 'whatsapp' && shop.whatsapp && order.status === 'placed' && (
                <Card className="mt-4">
                    <p className="text-fg">{t('market.order.whatsapp_step')}</p>
                    <a
                        href={`https://wa.me/${shop.whatsapp.replace('+', '')}?text=${whatsappText}`}
                        rel="noopener"
                        className="text-primary mt-2 inline-flex items-center gap-2 font-semibold hover:underline"
                    >
                        <MessageCircle className="size-4" aria-hidden /> {t('market.order.open_whatsapp')}
                    </a>
                </Card>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <CardTitle>{t('market.order.items')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {order.items.map((i, n) => (
                            <li key={n} className="text-fg flex justify-between gap-2">
                                <span>
                                    {i.quantity} × {i.title}
                                    {i.option ? ` (${i.option})` : ''}
                                </span>
                                <span>{formatMoney(i.unit_price_cents * i.quantity)}</span>
                            </li>
                        ))}
                        {order.fee_cents > 0 && (
                            <li className="text-fg flex justify-between gap-2">
                                <span>{t('market.order.delivery_fee')}</span>
                                <span>{formatMoney(order.fee_cents)}</span>
                            </li>
                        )}
                        <li className="text-fg border-line flex justify-between gap-2 border-t pt-1 font-semibold">
                            <span>{t('market.order.total')}</span>
                            <span>{formatMoney(order.total_cents)}</span>
                        </li>
                    </ul>
                    {order.note && <p className="text-fg-muted mt-3 text-sm">“{order.note}”</p>}
                    {order.address && (
                        <p className="text-fg mt-2 text-sm">
                            {t('market.order.deliver_to', { address: order.address })}
                        </p>
                    )}
                    {order.fulfilment === 'collection' && shop.collectionNote && (
                        <p className="text-fg mt-2 text-sm">
                            {t('market.order.collect_at', { place: shop.collectionNote })}
                        </p>
                    )}
                    <p className="text-fg-muted mt-2 text-xs">{t('market.checkout.cash_note')}</p>
                </Card>
                <Card className="h-fit">
                    {viewer === 'trader' && buyer && (
                        <p className="text-fg text-sm">
                            {t('market.order.buyer', { name: buyer.name })} · {buyer.phone ?? buyer.phoneMasked}
                            {!buyer.phone && (
                                <span className="text-fg-muted block text-xs">{t('market.order.phone_private')}</span>
                            )}
                        </p>
                    )}
                    {viewer === 'trader' && moves.length > 0 && (
                        <div className="mt-3 flex flex-col gap-2">
                            {moves.includes('accepted') && (
                                <Field label={t('market.order.eta_label')}>
                                    <Input value={eta} onChange={(e) => setEta(e.target.value)} />
                                </Field>
                            )}
                            {(moves.includes('declined') || moves.includes('cancelled')) && (
                                <Field label={t('market.order.reason_label')}>
                                    <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                                </Field>
                            )}
                            <div className="flex flex-wrap gap-2">
                                {moves.map((m) => (
                                    <Button
                                        key={m}
                                        size="sm"
                                        variant={['declined', 'cancelled'].includes(m) ? 'ghost' : 'primary'}
                                        onClick={() => move(m)}
                                    >
                                        {t(`market.order.move.${m}`)}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                    {viewer === 'buyer' && order.status === 'placed' && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.post(`/market/orders/${order.id}/cancel`, {}, { preserveScroll: true })
                            }
                        >
                            {t('market.order.cancel')}
                        </Button>
                    )}
                    {viewer === 'buyer' &&
                        ['ready', 'out_for_delivery', 'completed'].includes(order.status) &&
                        !order.buyerConfirmed && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(`/market/orders/${order.id}/received`, {}, { preserveScroll: true })
                                }
                            >
                                {t('market.order.received')}
                            </Button>
                        )}
                    <CardTitle className="mt-4">{t('market.order.messages')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2 text-sm">
                        {messages.map((m) => (
                            <li key={m.id} className={`rounded p-2 ${m.mine ? 'bg-primary-soft' : 'bg-surface-muted'}`}>
                                <p className="text-fg whitespace-pre-line">{m.body}</p>
                                <p className="text-fg-muted text-xs">
                                    {formatDateTime(m.at)}
                                    {m.held ? ` · ${t('market.message.held_badge')}` : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <form
                        className="mt-2 flex flex-col gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            message.post(`/market/orders/${order.id}/messages`, {
                                preserveScroll: true,
                                onSuccess: () => message.reset(),
                            });
                        }}
                    >
                        <Field label={t('market.order.write')}>
                            <Textarea
                                rows={2}
                                value={message.data.body}
                                onChange={(e) => message.setData('body', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button
                                type="submit"
                                size="sm"
                                variant="secondary"
                                disabled={!message.data.body.trim()}
                                loading={message.processing}
                            >
                                {t('work.messages.send')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
