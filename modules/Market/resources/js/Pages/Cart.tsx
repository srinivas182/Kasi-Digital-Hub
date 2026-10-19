import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Line {
    id: number;
    productId: string;
    title: string;
    option: string | null;
    quantity: number;
    unit_price_cents: number;
    problem: string | null;
}

interface Group {
    shop: {
        id: string;
        name: string;
        slug: string;
        collection: boolean;
        collectionNote: string | null;
        delivery: boolean;
        deliveryArea: string | null;
        deliveryFee: number;
        minOrder: number;
        whatsapp: boolean;
    };
    lines: Line[];
    items_cents: number;
}

function Checkout({ group, canOrder }: { group: Group; canOrder: boolean }) {
    const { t } = useTranslation();
    const s = group.shop;
    const form = useForm({
        fulfilment: s.collection ? 'collection' : 'delivery',
        address: '',
        note: '',
        channel: 'app',
        share_phone: false,
    });
    const fee = form.data.fulfilment === 'delivery' ? s.deliveryFee : 0;
    const blocked = group.lines.some((l) => l.problem !== null) || group.items_cents < s.minOrder || !canOrder;
    const send = (channel: string) => {
        form.transform((d) => ({ ...d, channel, address: d.fulfilment === 'delivery' ? d.address : null }));
        form.post(`/market/cart/shops/${s.id}/order`);
    };
    return (
        <form
            className="border-line mt-4 flex flex-col gap-3 border-t pt-3"
            onSubmit={(e) => {
                e.preventDefault();
                send('app');
            }}
        >
            <fieldset>
                <legend className="text-fg text-sm font-semibold">{t('market.checkout.how')}</legend>
                {s.collection && (
                    <label className="text-fg mt-1 flex items-start gap-2 text-sm">
                        <input
                            type="radio"
                            name={`f-${s.id}`}
                            checked={form.data.fulfilment === 'collection'}
                            onChange={() => form.setData('fulfilment', 'collection')}
                        />
                        <span>
                            {t('market.checkout.collect')}
                            {s.collectionNote && <span className="text-fg-muted block">{s.collectionNote}</span>}
                        </span>
                    </label>
                )}
                {s.delivery && (
                    <label className="text-fg mt-1 flex items-start gap-2 text-sm">
                        <input
                            type="radio"
                            name={`f-${s.id}`}
                            checked={form.data.fulfilment === 'delivery'}
                            onChange={() => form.setData('fulfilment', 'delivery')}
                        />
                        <span>
                            {t('market.checkout.deliver', { fee: formatMoney(s.deliveryFee) })}
                            {s.deliveryArea && <span className="text-fg-muted block">{s.deliveryArea}</span>}
                        </span>
                    </label>
                )}
            </fieldset>
            {form.data.fulfilment === 'delivery' && (
                <Field label={t('market.checkout.address')}>
                    <Input value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                </Field>
            )}
            <Field label={t('market.checkout.note')}>
                <Textarea rows={2} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
            </Field>
            <Checkbox
                label={t('market.checkout.share_phone')}
                checked={form.data.share_phone}
                onCheckedChange={(c) => form.setData('share_phone', c === true)}
            />
            <p className="text-fg font-semibold">
                {t('market.checkout.total', { total: formatMoney(group.items_cents + fee) })}
            </p>
            <p className="text-fg-muted text-xs">{t('market.checkout.cash_note')}</p>
            {group.items_cents < s.minOrder && (
                <Alert tone="warning" title={t('market.checkout.minimum', { min: formatMoney(s.minOrder) })} />
            )}
            <div className="flex flex-wrap gap-2">
                <Button type="submit" loading={form.processing} disabled={blocked}>
                    {t('market.checkout.place')}
                </Button>
                {s.whatsapp && (
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={blocked || form.processing}
                        onClick={() => send('whatsapp')}
                    >
                        {t('market.checkout.whatsapp')}
                    </Button>
                )}
            </div>
        </form>
    );
}

/** The cart, one checkout per shop (S25). */
export default function CartPage({ groups, canOrder }: { groups: Group[]; canOrder: boolean }) {
    const { t } = useTranslation();
    const { auth, errors, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.cart.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('market.cart.title')}</h1>
            {!canOrder && (
                <div className="mt-3">
                    <Alert tone="info" title={t('market.order.age')} />
                </div>
            )}
            {(errors?.order || errors?.cart) && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.order ?? errors.cart ?? ''} />
                </div>
            )}
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {groups.length === 0 && (
                <p className="text-fg-muted mt-4">
                    {t('market.cart.empty')}{' '}
                    <Link href="/market" className="text-primary font-semibold hover:underline">
                        {t('nav.market.browse')}
                    </Link>
                </p>
            )}
            <div className="mt-4 flex flex-col gap-6">
                {groups.map((g) => (
                    <Card key={g.shop.id}>
                        <CardTitle>
                            <Link href={`/market/shops/${g.shop.slug}`} className="hover:underline">
                                {g.shop.name}
                            </Link>
                        </CardTitle>
                        <ul className="mt-2 flex flex-col gap-2">
                            {g.lines.map((l) => (
                                <li key={l.id} className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-fg">
                                        {l.title}
                                        {l.option ? ` · ${l.option}` : ''}
                                        {l.problem && (
                                            <span className="text-danger-text block text-sm">
                                                {t(`market.cart.problem.${l.problem}`)}
                                            </span>
                                        )}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <label className="sr-only" htmlFor={`q-${l.id}`}>
                                            {t('market.quantity')}
                                        </label>
                                        <input
                                            id={`q-${l.id}`}
                                            type="number"
                                            min={0}
                                            max={50}
                                            defaultValue={l.quantity}
                                            className="border-line bg-surface text-fg w-16 rounded border p-1 text-sm"
                                            onBlur={(e) =>
                                                router.patch(
                                                    `/market/cart/items/${l.id}`,
                                                    { quantity: Number(e.target.value) || 0 },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                        <span className="text-fg w-24 text-right">
                                            {formatMoney(l.unit_price_cents * l.quantity)}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <Checkout group={g} canOrder={canOrder} />
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
