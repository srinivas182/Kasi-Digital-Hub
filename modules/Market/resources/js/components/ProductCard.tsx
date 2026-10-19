import { Link } from '@inertiajs/react';

import { Badge } from '@/components/ui/display';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export interface CardProduct {
    id: string;
    title: string;
    price_cents: number;
    soldOut: boolean;
    shop: string;
    shopSlug: string;
    photo: string | null;
    alt: string | null;
}

/** A product in a list: small picture (lazy, ~15 KB), title, price, shop. */
export function ProductCard({ p }: { p: CardProduct }) {
    const { t } = useTranslation();
    return (
        <Link
            href={`/market/products/${p.id}`}
            className="border-line bg-surface hover:bg-surface-muted flex h-full flex-col overflow-hidden rounded-lg border"
        >
            {p.photo ? (
                <img
                    src={p.photo}
                    alt={p.alt ?? ''}
                    loading="lazy"
                    decoding="async"
                    width={320}
                    height={240}
                    className="aspect-[4/3] w-full object-cover"
                />
            ) : (
                <div className="bg-surface-muted text-fg-muted flex aspect-[4/3] items-center justify-center text-sm">
                    {t('market.no_photo')}
                </div>
            )}
            <div className="flex flex-1 flex-col gap-1 p-3">
                <span className="text-fg font-semibold">{p.title}</span>
                <span className="text-fg">{formatMoney(p.price_cents)}</span>
                <span className="text-fg-muted text-sm">{p.shop}</span>
                {p.soldOut && <Badge tone="warning">{t('market.sold_out')}</Badge>}
            </div>
        </Link>
    );
}
