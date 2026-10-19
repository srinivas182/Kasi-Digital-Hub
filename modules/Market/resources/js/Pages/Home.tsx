import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { type CardProduct, ProductCard } from '../components/ProductCard';

interface Props {
    categories: { id: number; slug: string }[];
    newest: CardProduct[];
    shops: { name: string; slug: string; logo: string | null; hub: string | null }[];
}

/** KasiMarket home (S24): search, categories, newest products, shops. Public and low-data. */
export default function MarketHome({ categories, newest, shops }: Props) {
    const { t } = useTranslation();
    const [q, setQ] = useState('');
    const { auth } = usePage().props;
    return (
        <PublicLayout>
            <Head title={t('market.title')} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-fg text-3xl font-bold">{t('market.title')}</h1>
                    {auth.user && (
                        <span className="flex gap-4 text-sm font-semibold">
                            <Link href="/market/cart" className="text-primary hover:underline">
                                {t('market.cart.title')}
                            </Link>
                            <Link href="/market/orders" className="text-primary hover:underline">
                                {t('market.orders.title')}
                            </Link>
                        </span>
                    )}
                </div>
                <p className="text-fg-muted mt-1">{t('market.lead')}</p>
                <form
                    role="search"
                    className="mt-5 flex flex-wrap items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/market/search', q ? { q } : {});
                    }}
                >
                    <Field label={t('market.search_label')} className="min-w-56 flex-1">
                        <Input value={q} onChange={(e) => setQ(e.target.value)} />
                    </Field>
                    <Button type="submit">{t('market.search')}</Button>
                </form>
                <nav className="mt-6" aria-label={t('market.categories')}>
                    <ul className="flex flex-wrap gap-2">
                        {categories.map((c) => (
                            <li key={c.id}>
                                <Link
                                    href={`/market/search?category=${c.id}`}
                                    className="border-line text-fg hover:bg-surface-muted rounded-full border px-3 py-1 text-sm"
                                >
                                    {t(`market.category.${c.slug}`)}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
                <h2 className="text-fg mt-8 text-xl font-bold">{t('market.newest')}</h2>
                <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {newest.map((p) => (
                        <li key={p.id}>
                            <ProductCard p={p} />
                        </li>
                    ))}
                    {newest.length === 0 && <li className="text-fg-muted col-span-full">{t('market.none_yet')}</li>}
                </ul>
                {shops.length > 0 && (
                    <>
                        <h2 className="text-fg mt-8 text-xl font-bold">{t('market.shops')}</h2>
                        <ul className="mt-3 grid gap-3 sm:grid-cols-2">
                            {shops.map((s) => (
                                <li key={s.slug}>
                                    <Link href={`/market/shops/${s.slug}`}>
                                        <Card className="hover:bg-surface-muted flex items-center gap-3">
                                            {s.logo && (
                                                <img
                                                    src={s.logo}
                                                    alt=""
                                                    width={48}
                                                    height={48}
                                                    loading="lazy"
                                                    className="size-12 rounded-full object-cover"
                                                />
                                            )}
                                            <span>
                                                <span className="text-fg block font-semibold">{s.name}</span>
                                                {s.hub && <span className="text-fg-muted text-sm">{s.hub}</span>}
                                            </span>
                                        </Card>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </PublicLayout>
    );
}
