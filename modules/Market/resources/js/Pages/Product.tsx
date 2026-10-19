import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MessageCircle, Share2 } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    product: {
        id: string;
        title: string;
        price_cents: number;
        soldOut: boolean;
        description: string | null;
        unit: string;
        stock_type: string;
        options: { id: number; label: string; price_cents: number; soldOut: boolean }[];
        photos: { small: string; medium: string; alt: string }[];
    };
    shop: { name: string; slug: string; hub: string | null; whatsapp: string | null; away: boolean };
    shareUrl: string;
}

export default function MarketProduct({ product, shop, shareUrl }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const [photo, setPhoto] = useState(0);
    const [reporting, setReporting] = useState(false);
    const [option, setOption] = useState(String(product.options.find((o) => !o.soldOut)?.id ?? ''));
    const [quantity, setQuantity] = useState('1');
    const report = useForm({ reason: 'scam', note: '' });
    const message = encodeURIComponent(t('market.whatsapp_message', { title: product.title, url: shareUrl }));
    const current = product.photos[photo];
    return (
        <PublicLayout>
            <Head title={product.title} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <Link
                    href={`/market/shops/${shop.slug}`}
                    className="text-primary text-sm font-semibold hover:underline"
                >
                    ← {shop.name}
                </Link>
                {flash.status && (
                    <div className="mt-3">
                        <Alert tone="success" title={flash.status} />
                    </div>
                )}
                <div className="mt-3 grid gap-6 md:grid-cols-2">
                    <div>
                        {current ? (
                            <img
                                src={current.medium}
                                alt={current.alt}
                                width={800}
                                height={600}
                                className="aspect-[4/3] w-full rounded-lg object-cover"
                            />
                        ) : (
                            <div className="bg-surface-muted text-fg-muted flex aspect-[4/3] items-center justify-center rounded-lg">
                                {t('market.no_photo')}
                            </div>
                        )}
                        {product.photos.length > 1 && (
                            <div className="mt-2 flex gap-2">
                                {product.photos.map((p, i) => (
                                    <button
                                        key={p.small}
                                        type="button"
                                        onClick={() => setPhoto(i)}
                                        aria-label={t('market.photo_n', { n: i + 1 })}
                                        aria-pressed={i === photo}
                                    >
                                        <img
                                            src={p.small}
                                            alt=""
                                            width={80}
                                            height={60}
                                            loading="lazy"
                                            className={`h-15 w-20 rounded object-cover ${i === photo ? 'ring-primary ring-2' : ''}`}
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    <div>
                        <h1 className="text-fg text-2xl font-bold">{product.title}</h1>
                        <p className="text-fg mt-1 text-xl font-semibold">
                            {formatMoney(product.price_cents)}{' '}
                            <span className="text-fg-muted text-sm font-normal">
                                {t(`market.unit.${product.unit}`)}
                            </span>
                        </p>
                        {product.soldOut && <Badge tone="warning">{t('market.sold_out')}</Badge>}
                        {shop.away && <Alert tone="warning" title={t('market.shop.away_notice')} />}
                        {product.options.length > 0 && (
                            <Field label={t('market.options')} className="mt-3">
                                <Select value={option} onChange={(e) => setOption(e.target.value)}>
                                    {product.options.map((o) => (
                                        <option key={o.label} value={o.id} disabled={o.soldOut}>
                                            {o.label} - {formatMoney(o.price_cents)}
                                            {o.soldOut ? ` (${t('market.sold_out')})` : ''}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        )}
                        {product.description && (
                            <p className="text-fg mt-3 whitespace-pre-line">{product.description}</p>
                        )}
                        {!product.soldOut && (
                            <div className="mt-4 flex flex-wrap items-end gap-2">
                                <Field label={t('market.quantity')}>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={50}
                                        inputMode="numeric"
                                        className="w-24"
                                        value={quantity}
                                        onChange={(e) => setQuantity(e.target.value)}
                                    />
                                </Field>
                                <Button
                                    onClick={() =>
                                        auth.user
                                            ? router.post(
                                                  `/market/cart/${product.id}`,
                                                  {
                                                      quantity: Number(quantity) || 1,
                                                      option_id: option ? Number(option) : null,
                                                  },
                                                  { preserveScroll: true },
                                              )
                                            : router.visit('/login')
                                    }
                                >
                                    {t('market.cart.add')}
                                </Button>
                                {auth.user && (
                                    <Link
                                        href="/market/cart"
                                        className="text-primary text-sm font-semibold hover:underline"
                                    >
                                        {t('market.cart.view')}
                                    </Link>
                                )}
                            </div>
                        )}
                        <Card className="mt-4">
                            {shop.whatsapp ? (
                                <a
                                    href={`https://wa.me/${shop.whatsapp.replace('+', '')}?text=${message}`}
                                    rel="noopener"
                                    className="text-primary inline-flex items-center gap-2 font-semibold hover:underline"
                                >
                                    <MessageCircle className="size-4" aria-hidden /> {t('market.order_whatsapp')}
                                </a>
                            ) : (
                                <p className="text-fg-muted text-sm">{t('market.order_soon')}</p>
                            )}
                            <a
                                href={`https://wa.me/?text=${encodeURIComponent(`${product.title} - ${shareUrl}`)}`}
                                rel="noopener"
                                className="text-fg mt-3 flex items-center gap-2 text-sm hover:underline"
                            >
                                <Share2 className="size-4" aria-hidden /> {t('market.share')}
                            </a>
                        </Card>
                        {auth.user &&
                            (reporting ? (
                                <form
                                    className="mt-4 flex flex-col gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        report.post(`/market/products/${product.id}/report`, {
                                            preserveScroll: true,
                                            onSuccess: () => setReporting(false),
                                        });
                                    }}
                                >
                                    <Field label={t('market.report.why')}>
                                        <Select
                                            value={report.data.reason}
                                            onChange={(e) => report.setData('reason', e.target.value)}
                                        >
                                            {['prohibited', 'scam', 'wrong_info', 'offensive', 'other'].map((r) => (
                                                <option key={r} value={r}>
                                                    {t(`market.report.reason.${r}`)}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label={t('market.report.note')}>
                                        <Textarea
                                            rows={2}
                                            value={report.data.note}
                                            onChange={(e) => report.setData('note', e.target.value)}
                                        />
                                    </Field>
                                    <div>
                                        <Button type="submit" size="sm" variant="secondary" loading={report.processing}>
                                            {t('market.report.send')}
                                        </Button>
                                    </div>
                                </form>
                            ) : (
                                <button
                                    type="button"
                                    className="text-fg-muted mt-4 text-sm underline"
                                    onClick={() => setReporting(true)}
                                >
                                    {t('market.report.link')}
                                </button>
                            ))}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
