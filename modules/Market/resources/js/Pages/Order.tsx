import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
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
        payment: string;
        paymentStatus: string;
        refunded_cents: number;
        collectionHub: { name: string; address: string | null } | null;
    };
    shop: { name: string; slug: string; collectionNote: string | null; whatsapp: string | null };
    buyer: { name: string; phone: string | null; phoneMasked: string } | null;
    viewer: 'buyer' | 'trader';
    moves: string[];
    messages: { id: string; mine: boolean; body: string; held: boolean; at: string }[];
    canReview: boolean;
    review: { rating: number; body: string | null; held: boolean } | null;
    canDispute: boolean;
    dispute: {
        id: string;
        status: string;
        reason: string;
        details: string;
        response: string | null;
        outcome: string | null;
        note: string | null;
        respondBy: string;
    } | null;
    canNoShow: boolean;
}

/** One order, for its buyer or its trader (S25). */
export default function OrderPage({
    order,
    shop,
    buyer,
    viewer,
    moves,
    messages,
    canReview,
    review,
    canDispute,
    dispute,
    canNoShow,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [reason, setReason] = useState('');
    const [eta, setEta] = useState(order.eta ?? '');
    const refund = useForm({ amount: '', reason: '' });
    const reviewForm = useForm({ rating: 5, body: '' });
    const disputeForm = useForm<{ reason: string; details: string; photos: File[] }>({
        reason: 'not_received',
        details: '',
        photos: [],
    });
    const responseForm = useForm<{ response: string; photos: File[] }>({ response: '', photos: [] });
    const [disputing, setDisputing] = useState(false);
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
            {(errors?.order || errors?.trust) && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.order ?? errors.trust ?? ''} />
                </div>
            )}
            <p className="mt-2 flex flex-wrap gap-2">
                <Badge
                    tone={
                        order.paymentStatus === 'paid'
                            ? 'success'
                            : order.paymentStatus === 'pending'
                              ? 'warning'
                              : 'neutral'
                    }
                >
                    {order.payment === 'online' ? t(`market.pay.status.${order.paymentStatus}`) : t('market.pay.cash')}
                </Badge>
                {order.refunded_cents > 0 && (
                    <Badge tone="neutral">
                        {t('market.pay.refunded', { amount: formatMoney(order.refunded_cents) })}
                    </Badge>
                )}
            </p>
            {order.collectionHub && viewer === 'buyer' && (
                <div className="mt-3">
                    <Alert
                        tone="info"
                        title={
                            order.status === 'at_hub'
                                ? t('market.order.at_hub_buyer', { hub: order.collectionHub.name })
                                : t('market.order.hub_buyer', { hub: order.collectionHub.name })
                        }
                    />
                    {order.collectionHub.address && (
                        <p className="text-fg-muted mt-1 text-sm">{order.collectionHub.address}</p>
                    )}
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
            {dispute && (
                <Card className="mt-4">
                    <CardTitle>{t('market.dispute.title')}</CardTitle>
                    <p className="text-fg mt-1 text-sm">
                        <Badge
                            tone={dispute.status === 'resolved' || dispute.status === 'settled' ? 'success' : 'warning'}
                        >
                            {t(`market.dispute.status.${dispute.status}`)}
                        </Badge>{' '}
                        {t(`market.dispute.reason.${dispute.reason}`)}
                    </p>
                    <p className="text-fg mt-2 text-sm whitespace-pre-line">{dispute.details}</p>
                    {dispute.response && (
                        <p className="text-fg mt-2 text-sm">
                            <span className="font-semibold">{t('market.dispute.trader_said')}</span> {dispute.response}
                        </p>
                    )}
                    {dispute.outcome && (
                        <p className="text-fg mt-2 text-sm">
                            <span className="font-semibold">{t(`market.dispute.outcome.${dispute.outcome}`)}</span>
                            {dispute.note ? ` - ${dispute.note}` : ''}
                        </p>
                    )}
                    {viewer === 'buyer' && ['open', 'responded'].includes(dispute.status) && (
                        <Button
                            className="mt-3"
                            size="sm"
                            variant="secondary"
                            onClick={() =>
                                router.post(`/market/disputes/${dispute.id}/settle`, {}, { preserveScroll: true })
                            }
                        >
                            {t('market.dispute.settle')}
                        </Button>
                    )}
                    {viewer === 'trader' && ['open', 'responded'].includes(dispute.status) && (
                        <form
                            className="mt-3 flex flex-col gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                responseForm.post(`/market/shop/disputes/${dispute.id}/respond`, {
                                    forceFormData: true,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Field
                                label={t('market.dispute.your_response')}
                                hint={t('market.dispute.respond_by', { date: formatDateTime(dispute.respondBy) })}
                            >
                                <Textarea
                                    rows={3}
                                    value={responseForm.data.response}
                                    onChange={(e) => responseForm.setData('response', e.target.value)}
                                />
                            </Field>
                            <Field label={t('market.dispute.photos')}>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    aria-label={t('market.dispute.photos')}
                                    onChange={(e) =>
                                        responseForm.setData('photos', Array.from(e.target.files ?? []).slice(0, 4))
                                    }
                                />
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={responseForm.data.response.trim().length < 5}
                                    loading={responseForm.processing}
                                >
                                    {t('market.dispute.send_response')}
                                </Button>
                            </div>
                        </form>
                    )}
                </Card>
            )}
            {viewer === 'buyer' && (canReview || review) && (
                <Card className="mt-4">
                    <CardTitle>{t('market.review.title')}</CardTitle>
                    {review ? (
                        <p className="text-fg mt-1 text-sm">
                            {'★'.repeat(review.rating)}
                            {'☆'.repeat(5 - review.rating)} {review.body}
                            {review.held && (
                                <span className="text-fg-muted block text-xs">{t('market.message.held_badge')}</span>
                            )}
                        </p>
                    ) : (
                        <form
                            className="mt-2 flex flex-col gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                reviewForm.post(`/market/orders/${order.id}/review`, { preserveScroll: true });
                            }}
                        >
                            <fieldset>
                                <legend className="text-fg text-sm font-semibold">{t('market.review.stars')}</legend>
                                <div className="mt-1 flex gap-3">
                                    {[1, 2, 3, 4, 5].map((n) => (
                                        <label key={n} className="text-fg flex items-center gap-1 text-sm">
                                            <input
                                                type="radio"
                                                name="rating"
                                                checked={reviewForm.data.rating === n}
                                                onChange={() => reviewForm.setData('rating', n)}
                                            />
                                            {t('market.review.n_stars', { n })}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <Field label={t('market.review.text')}>
                                <Textarea
                                    rows={2}
                                    maxLength={500}
                                    value={reviewForm.data.body}
                                    onChange={(e) => reviewForm.setData('body', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" size="sm" loading={reviewForm.processing}>
                                    {t('market.review.send')}
                                </Button>
                            </div>
                        </form>
                    )}
                </Card>
            )}
            {viewer === 'buyer' && canDispute && !dispute && (
                <div className="mt-4">
                    {disputing ? (
                        <Card>
                            <CardTitle>{t('market.dispute.open_title')}</CardTitle>
                            <form
                                className="mt-2 flex flex-col gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    disputeForm.post(`/market/orders/${order.id}/dispute`, {
                                        forceFormData: true,
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <Field label={t('market.dispute.what')}>
                                    <Select
                                        value={disputeForm.data.reason}
                                        onChange={(e) => disputeForm.setData('reason', e.target.value)}
                                    >
                                        {['not_received', 'not_described', 'damaged', 'charged_wrongly', 'other'].map(
                                            (r) => (
                                                <option key={r} value={r}>
                                                    {t(`market.dispute.reason.${r}`)}
                                                </option>
                                            ),
                                        )}
                                    </Select>
                                </Field>
                                <Field label={t('market.dispute.details')} error={disputeForm.errors.details}>
                                    <Textarea
                                        rows={3}
                                        value={disputeForm.data.details}
                                        onChange={(e) => disputeForm.setData('details', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('market.dispute.photos')}>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        aria-label={t('market.dispute.photos')}
                                        onChange={(e) =>
                                            disputeForm.setData('photos', Array.from(e.target.files ?? []).slice(0, 4))
                                        }
                                    />
                                </Field>
                                <p className="text-fg-muted text-xs">{t('market.dispute.how_it_works')}</p>
                                <div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={disputeForm.data.details.trim().length < 10}
                                        loading={disputeForm.processing}
                                    >
                                        {t('market.dispute.open')}
                                    </Button>
                                </div>
                            </form>
                        </Card>
                    ) : (
                        <button
                            type="button"
                            className="text-fg-muted text-sm underline"
                            onClick={() => setDisputing(true)}
                        >
                            {t('market.dispute.problem')}
                        </button>
                    )}
                </div>
            )}
            {viewer === 'trader' && canNoShow && (
                <p className="mt-3 text-sm">
                    <button
                        type="button"
                        className="text-fg-muted underline"
                        onClick={() =>
                            router.post(`/market/shop/orders/${order.id}/no-show`, {}, { preserveScroll: true })
                        }
                    >
                        {t('market.noshow.button')}
                    </button>
                </p>
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
                    {viewer === 'trader' && ['paid', 'part_refunded'].includes(order.paymentStatus) && (
                        <form
                            className="border-line mt-4 flex flex-col gap-2 border-t pt-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                refund.transform((d) => ({
                                    amount_cents: Math.round(Number(d.amount) * 100),
                                    reason: d.reason,
                                }));
                                refund.post(`/market/shop/orders/${order.id}/refund`, {
                                    preserveScroll: true,
                                    onSuccess: () => refund.reset(),
                                });
                            }}
                        >
                            <p className="text-fg text-sm font-semibold">{t('market.refund.title')}</p>
                            <Field label={t('market.refund.amount')}>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={refund.data.amount}
                                    onChange={(e) => refund.setData('amount', e.target.value)}
                                />
                            </Field>
                            <Field label={t('market.refund.reason')}>
                                <Input
                                    value={refund.data.reason}
                                    onChange={(e) => refund.setData('reason', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    disabled={!refund.data.amount || refund.data.reason.length < 3}
                                >
                                    {t('market.refund.send')}
                                </Button>
                            </div>
                        </form>
                    )}
                    {viewer === 'buyer' && (
                        <p className="mt-4 text-sm">
                            <Link
                                href={`/help/requests?subject=${encodeURIComponent(t('market.order.help_subject', { number: order.number }))}`}
                                className="text-primary font-semibold hover:underline"
                            >
                                {t('market.order.get_help')}
                            </Link>
                        </p>
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
