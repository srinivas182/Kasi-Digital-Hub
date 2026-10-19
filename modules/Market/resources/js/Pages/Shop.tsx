import { Head, Link } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Badge, Card } from '@/components/ui/display';
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
    };
    products: CardProduct[];
}

export default function MarketShop({ shop, products }: Props) {
    const { t } = useTranslation();
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
