import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Field, Input, Select } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { type CardProduct, ProductCard } from '../components/ProductCard';

interface Props {
    results: { data: CardProduct[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: {
        q?: string;
        category?: number | string;
        min?: number | string;
        max?: number | string;
        in_stock?: boolean | string;
    };
    categories: { id: number; slug: string }[];
}

export default function MarketSearch({ results, filters, categories }: Props) {
    const { t } = useTranslation();
    const [f, setF] = useState({
        q: filters.q ?? '',
        category: String(filters.category ?? ''),
        min: String(filters.min ?? ''),
        max: String(filters.max ?? ''),
        in_stock: Boolean(filters.in_stock),
    });
    const go = () =>
        router.get(
            '/market/search',
            Object.fromEntries(Object.entries({ ...f, in_stock: f.in_stock ? 1 : '' }).filter(([, v]) => v !== '')),
            { preserveScroll: true },
        );
    return (
        <PublicLayout>
            <Head title={t('market.search_title')} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <Link href="/market" className="text-primary text-sm font-semibold hover:underline">
                    ← {t('market.title')}
                </Link>
                <h1 className="text-fg mt-2 text-2xl font-bold">{t('market.search_title')}</h1>
                <form
                    role="search"
                    className="mt-4 grid gap-3 sm:grid-cols-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        go();
                    }}
                >
                    <Field label={t('market.search_label')} className="sm:col-span-2">
                        <Input value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} />
                    </Field>
                    <Field label={t('market.category_label')}>
                        <Select value={f.category} onChange={(e) => setF({ ...f, category: e.target.value })}>
                            <option value="">{t('market.all_categories')}</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {t(`market.category.${c.slug}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('market.min_price')}>
                        <Input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={f.min}
                            onChange={(e) => setF({ ...f, min: e.target.value })}
                        />
                    </Field>
                    <Field label={t('market.max_price')}>
                        <Input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={f.max}
                            onChange={(e) => setF({ ...f, max: e.target.value })}
                        />
                    </Field>
                    <Checkbox
                        label={t('market.in_stock')}
                        checked={f.in_stock}
                        onCheckedChange={(c) => setF({ ...f, in_stock: c === true })}
                    />
                    <div className="sm:col-span-4">
                        <Button type="submit">{t('market.search')}</Button>
                    </div>
                </form>
                <p className="text-fg-muted mt-5 text-sm" role="status">
                    {t('market.results', { count: results.total })}
                </p>
                <ul className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {results.data.map((p) => (
                        <li key={p.id}>
                            <ProductCard p={p} />
                        </li>
                    ))}
                </ul>
                {results.links.length > 3 && (
                    <nav className="mt-6 flex flex-wrap gap-2" aria-label={t('work.board.pages')}>
                        {results.links.map((l, i) =>
                            l.url ? (
                                <Link
                                    key={i}
                                    href={l.url}
                                    preserveScroll
                                    className={`rounded border px-3 py-1 text-sm ${l.active ? 'bg-primary text-primary-fg' : 'border-line text-fg'}`}
                                    aria-current={l.active ? 'page' : undefined}
                                >
                                    <span dangerouslySetInnerHTML={{ __html: l.label }} />
                                </Link>
                            ) : null,
                        )}
                    </nav>
                )}
            </div>
        </PublicLayout>
    );
}
