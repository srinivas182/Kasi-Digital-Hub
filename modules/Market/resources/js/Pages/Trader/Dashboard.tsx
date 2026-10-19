import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Row {
    id: string;
    title: string;
    price_cents: number;
    status: string;
    reason: string | null;
    stock_type: string;
    stock: number | null;
    soldOut: boolean;
    views: number;
    photo: string | null;
}

interface Props {
    shop: {
        slug: string;
        name: string;
        description: string | null;
        area: string | null;
        whatsapp: string | null;
        whatsapp_public: boolean;
        away: boolean;
        logo: string | null;
        offers_collection: boolean;
        collection_note: string | null;
        offers_delivery: boolean;
        delivery_area: string | null;
        delivery_fee_cents: number;
        min_order_cents: number;
    };
    newOrders: number;
    badges: string[];
    products: Row[];
    reviewed: { done: number; of: number };
}

const tone = (s: string) =>
    s === 'published' ? 'success' : s === 'refused' ? 'danger' : s === 'review' ? 'warning' : 'neutral';

export default function TraderDashboard({ shop, badges, products, reviewed, newOrders }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const form = useForm({
        name: shop.name,
        description: shop.description ?? '',
        area: shop.area ?? '',
        whatsapp: shop.whatsapp ?? '',
        whatsapp_public: shop.whatsapp_public,
        away: shop.away,
        offers_collection: shop.offers_collection,
        collection_note: shop.collection_note ?? '',
        offers_delivery: shop.offers_delivery,
        delivery_area: shop.delivery_area ?? '',
        delivery_fee: (shop.delivery_fee_cents / 100).toString(),
        min_order: (shop.min_order_cents / 100).toString(),
    });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.dash.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{shop.name}</h1>
                    <p className="mt-1 flex flex-wrap gap-1">
                        {badges.map((b) => (
                            <Badge key={b} tone="success">
                                {t(`market.badge.${b}`)}
                            </Badge>
                        ))}
                        {shop.away && <Badge tone="warning">{t('market.dash.away')}</Badge>}
                    </p>
                </div>
                <span className="flex gap-2">
                    <Link href={`/market/shops/${shop.slug}`} className={buttonVariants({ variant: 'secondary' })}>
                        {t('market.dash.view_shop')}
                    </Link>
                    <Link href="/market/shop/orders" className={buttonVariants({ variant: 'secondary' })}>
                        {t('market.trader_orders.title')}
                        {newOrders > 0 ? ` (${newOrders})` : ''}
                    </Link>
                    <Link href="/market/shop/products/new" className={buttonVariants({})}>
                        <Plus className="size-4" aria-hidden /> {t('market.dash.add')}
                    </Link>
                </span>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="info" title={flash.status} />
                </div>
            )}
            {reviewed.done < reviewed.of && (
                <div className="mt-3">
                    <Alert
                        tone="info"
                        title={t('market.dash.first_review', { done: reviewed.done, of: reviewed.of })}
                    />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <CardTitle>{t('market.dash.products')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2">
                        {products.map((p) => (
                            <li
                                key={p.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                            >
                                <span className="flex items-center gap-3">
                                    {p.photo && (
                                        <img
                                            src={p.photo}
                                            alt=""
                                            width={56}
                                            height={42}
                                            loading="lazy"
                                            className="h-10 w-14 rounded object-cover"
                                        />
                                    )}
                                    <span>
                                        <Link
                                            href={`/market/shop/products/${p.id}`}
                                            className="text-primary font-semibold hover:underline"
                                        >
                                            {p.title}
                                        </Link>
                                        <span className="text-fg-muted block text-sm">
                                            {formatMoney(p.price_cents)} · {t('market.dash.views', { count: p.views })}
                                            {p.stock_type === 'quantity'
                                                ? ` · ${t('market.dash.stock', { count: p.stock ?? 0 })}`
                                                : ''}
                                        </span>
                                        {p.reason && <span className="text-fg-muted block text-sm">{p.reason}</span>}
                                    </span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <Badge tone={tone(p.soldOut && p.status === 'published' ? 'review' : p.status)}>
                                        {p.soldOut && p.status === 'published'
                                            ? t('market.sold_out')
                                            : t(`market.status.${p.status}`)}
                                    </Badge>
                                    {p.status === 'draft' && (
                                        <Button
                                            size="sm"
                                            onClick={() => router.post(`/market/shop/products/${p.id}/publish`)}
                                        >
                                            {t('market.dash.publish')}
                                        </Button>
                                    )}
                                    {(p.status === 'published' || p.status === 'hidden') && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    `/market/shop/products/${p.id}/hide`,
                                                    { hidden: p.status === 'published' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {p.status === 'published' ? t('market.dash.hide') : t('market.dash.show')}
                                        </Button>
                                    )}
                                </span>
                            </li>
                        ))}
                        {products.length === 0 && <li className="text-fg-muted">{t('market.dash.no_products')}</li>}
                    </ul>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('market.dash.settings')}</CardTitle>
                    <form
                        className="mt-3 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({
                                ...d,
                                delivery_fee_cents: Math.round(Number(d.delivery_fee || 0) * 100),
                                min_order_cents: Math.round(Number(d.min_order || 0) * 100),
                            }));
                            form.put('/market/shop', { preserveScroll: true });
                        }}
                    >
                        <Field label={t('market.open.name')}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label={t('market.open.description')}>
                            <Textarea
                                rows={3}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <Field label={t('market.open.area')}>
                            <Input value={form.data.area} onChange={(e) => form.setData('area', e.target.value)} />
                        </Field>
                        <Field label={t('market.open.whatsapp')}>
                            <Input
                                type="tel"
                                value={form.data.whatsapp}
                                onChange={(e) => form.setData('whatsapp', e.target.value)}
                            />
                        </Field>
                        <Checkbox
                            label={t('market.open.whatsapp_public')}
                            checked={form.data.whatsapp_public}
                            onCheckedChange={(c) => form.setData('whatsapp_public', c === true)}
                        />
                        <Checkbox
                            label={t('market.dash.away_switch')}
                            checked={form.data.away}
                            onCheckedChange={(c) => form.setData('away', c === true)}
                        />
                        <Checkbox
                            label={t('market.dash.offers_collection')}
                            checked={form.data.offers_collection}
                            onCheckedChange={(c) => form.setData('offers_collection', c === true)}
                        />
                        {form.data.offers_collection && (
                            <Field label={t('market.dash.collection_note')}>
                                <Input
                                    value={form.data.collection_note}
                                    onChange={(e) => form.setData('collection_note', e.target.value)}
                                />
                            </Field>
                        )}
                        <Checkbox
                            label={t('market.dash.offers_delivery')}
                            checked={form.data.offers_delivery}
                            onCheckedChange={(c) => form.setData('offers_delivery', c === true)}
                        />
                        {form.data.offers_delivery && (
                            <>
                                <Field label={t('market.dash.delivery_area')}>
                                    <Input
                                        value={form.data.delivery_area}
                                        onChange={(e) => form.setData('delivery_area', e.target.value)}
                                    />
                                </Field>
                                <Field label={t('market.dash.delivery_fee')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.delivery_fee}
                                        onChange={(e) => form.setData('delivery_fee', e.target.value)}
                                    />
                                </Field>
                            </>
                        )}
                        <Field label={t('market.dash.min_order')}>
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.min_order}
                                onChange={(e) => form.setData('min_order', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button type="submit" size="sm" variant="secondary" loading={form.processing}>
                                {t('work.save')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
