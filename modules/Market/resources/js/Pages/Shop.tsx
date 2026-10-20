import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { MessageCircle } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card } from '@/components/ui/display';
import { Field, Select, Textarea } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { type CardProduct, ProductCard } from '../components/ProductCard';

interface Props {
    shop: {
        name: string;
        slug: string;
        logo: string | null;
        hub: string | null;
        description: string | null;
        area: string | null;
        hours: Record<string, string>;
        away: boolean;
        whatsapp: string | null;
        badges: string[];
        rating: number | null;
        reviewCount: number;
    };
    reviews: { rating: number; body: string | null; by: string; at: string; reply: string | null }[];
    products: CardProduct[];
}

export default function MarketShop({ shop, products, reviews }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const [reporting, setReporting] = useState(false);
    const report = useForm({ reason: 'scam', note: '' });
    return (
        <PublicLayout>
            <Head title={shop.name} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <Link href="/market" className="text-primary text-sm font-semibold hover:underline">
                    ← {t('market.title')}
                </Link>
                <div className="mt-3 flex flex-wrap items-center gap-3">
                    {shop.logo && (
                        <img
                            src={shop.logo}
                            alt=""
                            width={64}
                            height={64}
                            className="size-16 rounded-full object-cover"
                        />
                    )}
                    <div>
                        <h1 className="text-fg text-3xl font-bold">{shop.name}</h1>
                        <p className="text-fg-muted">{[shop.area, shop.hub].filter(Boolean).join(' · ')}</p>
                    </div>
                </div>
                <p className="text-fg mt-2 text-sm">
                    {shop.rating !== null
                        ? t('market.review.rating', { rating: shop.rating, count: shop.reviewCount })
                        : t('market.review.too_few')}
                </p>
                {flash.status && (
                    <div className="mt-2">
                        <Alert tone="success" title={flash.status} />
                    </div>
                )}
                <p className="mt-2 flex flex-wrap gap-1">
                    {shop.badges.map((b) => (
                        <Badge key={b} tone="success">
                            {t(`market.badge.${b}`)}
                        </Badge>
                    ))}
                </p>
                {shop.away && (
                    <div className="mt-3">
                        <Alert tone="warning" title={t('market.shop.away_notice')} />
                    </div>
                )}
                <div className="mt-4 grid gap-4 md:grid-cols-[1fr_18rem]">
                    <div>
                        {shop.description && <p className="text-fg">{shop.description}</p>}
                        <ul className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {products.map((p) => (
                                <li key={p.id}>
                                    <ProductCard p={p} />
                                </li>
                            ))}
                            {products.length === 0 && (
                                <li className="text-fg-muted col-span-full">{t('market.shop.empty')}</li>
                            )}
                        </ul>
                        <section className="mt-8" aria-labelledby="reviews">
                            <h2 id="reviews" className="text-fg text-xl font-bold">
                                {t('market.review.heading')}
                            </h2>
                            <ul className="mt-2 flex flex-col gap-3">
                                {reviews.map((r, n) => (
                                    <li key={n} className="border-line border-b pb-2 text-sm">
                                        <p className="text-fg">
                                            <span role="img" aria-label={t('market.review.n_stars', { n: r.rating })}>
                                                {'★'.repeat(r.rating)}
                                                {'☆'.repeat(5 - r.rating)}
                                            </span>{' '}
                                            <span className="font-semibold">{r.by}</span>
                                        </p>
                                        {r.body && <p className="text-fg">{r.body}</p>}
                                        {r.reply && (
                                            <p className="text-fg-muted mt-1">
                                                {t('market.review.reply_from', { shop: shop.name })} {r.reply}
                                            </p>
                                        )}
                                    </li>
                                ))}
                                {reviews.length === 0 && (
                                    <li className="text-fg-muted text-sm">{t('market.review.none')}</li>
                                )}
                            </ul>
                        </section>
                        {auth.user &&
                            (reporting ? (
                                <form
                                    className="mt-6 flex max-w-md flex-col gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        report.post(`/market/shops/${shop.slug}/report`, {
                                            preserveScroll: true,
                                            onSuccess: () => setReporting(false),
                                        });
                                    }}
                                >
                                    <Field label={t('market.report.why_shop')}>
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
                                        <Button type="submit" size="sm" variant="secondary">
                                            {t('market.report.send')}
                                        </Button>
                                    </div>
                                </form>
                            ) : (
                                <button
                                    type="button"
                                    className="text-fg-muted mt-6 text-sm underline"
                                    onClick={() => setReporting(true)}
                                >
                                    {t('market.report.shop_link')}
                                </button>
                            ))}
                    </div>
                    <Card className="h-fit">
                        <h2 className="text-fg font-semibold">{t('market.how_to_order')}</h2>
                        {shop.whatsapp ? (
                            <a
                                href={`https://wa.me/${shop.whatsapp.replace('+', '')}`}
                                rel="noopener"
                                className="text-primary mt-2 inline-flex items-center gap-2 font-semibold hover:underline"
                            >
                                <MessageCircle className="size-4" aria-hidden /> {t('market.whatsapp_shop')}
                            </a>
                        ) : (
                            <p className="text-fg-muted mt-2 text-sm">{t('market.order_soon')}</p>
                        )}
                        {Object.keys(shop.hours).length > 0 && (
                            <dl className="mt-4 grid grid-cols-2 gap-1 text-sm">
                                {Object.entries(shop.hours).map(([d, h]) => (
                                    <div key={d} className="contents">
                                        <dt className="text-fg-muted">{t(`market.day.${d}`)}</dt>
                                        <dd className="text-fg">{h}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </Card>
                </div>
            </div>
        </PublicLayout>
    );
}
