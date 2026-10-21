import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { DesignPreview, downloadPng, type Kit } from '../components/DesignPreview';

interface Product {
    id: string;
    title: string;
    price_cents: number;
    unit: string;
    photo: string | null;
    url: string;
}

interface Props {
    business: { id: string; name: string };
    kit: Kit;
    products: Product[];
    chosen: string[];
    built: { items: Product[]; message: string } | null;
    max: number;
}

function ProductImage({ p, kit, business }: { p: Product; kit: Kit; business: string }) {
    const { t } = useTranslation();
    const ref = useRef<SVGSVGElement>(null);
    return (
        <li className="flex flex-col gap-2">
            <DesignPreview
                ref={ref}
                kit={kit}
                template="square"
                size="square"
                fields={{ headline: p.title, text: formatMoney(p.price_cents) }}
                businessName={business}
                photo={p.photo}
            />
            <Button
                size="sm"
                variant="secondary"
                onClick={() => ref.current && void downloadPng(ref.current, `${p.title}.png`)}
            >
                {t('brand.design.download_png')}
            </Button>
        </li>
    );
}

/** The WhatsApp catalogue maker (S30): branded images and a message to send yourself. */
export default function Catalogue({ business, kit, products, chosen, built, max }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [picked, setPicked] = useState<string[]>(chosen);
    const [copied, setCopied] = useState(false);
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('brand.catalogue.title')} />
            <Link href="/brand" className="text-primary text-sm font-semibold hover:underline">
                ← {t('brand.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('brand.catalogue.title')}</h1>
            <p className="text-fg-muted mt-1">{t('brand.catalogue.lead')}</p>
            {products.length === 0 ? (
                <Card className="mt-4">
                    <p className="text-fg">{t('brand.catalogue.no_products')}</p>
                    <Link href="/market/shop" className="text-primary mt-2 inline-block font-semibold hover:underline">
                        {t('brand.catalogue.go_shop')}
                    </Link>
                </Card>
            ) : (
                <Card className="mt-4">
                    <CardTitle>{t('brand.catalogue.pick', { max })}</CardTitle>
                    <div className="mt-2 grid gap-1 sm:grid-cols-2">
                        {products.map((p) => (
                            <Checkbox
                                key={p.id}
                                label={`${p.title} - ${formatMoney(p.price_cents)}`}
                                checked={picked.includes(p.id)}
                                disabled={!picked.includes(p.id) && picked.length >= max}
                                onCheckedChange={(c) =>
                                    setPicked(c === true ? [...picked, p.id] : picked.filter((x) => x !== p.id))
                                }
                            />
                        ))}
                    </div>
                    <Button
                        className="mt-3"
                        disabled={picked.length === 0}
                        onClick={() => router.get('/brand/catalogue', { p: picked }, { preserveScroll: true })}
                    >
                        {t('brand.catalogue.make')}
                    </Button>
                </Card>
            )}
            {built && (
                <>
                    <Card className="mt-4">
                        <CardTitle>{t('brand.catalogue.message')}</CardTitle>
                        <pre className="text-fg mt-2 text-sm whitespace-pre-wrap">{built.message}</pre>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                onClick={() => {
                                    void navigator.clipboard?.writeText(built.message);
                                    setCopied(true);
                                }}
                            >
                                {t('brand.catalogue.copy')}
                            </Button>
                            <a
                                href={`https://wa.me/?text=${encodeURIComponent(built.message)}`}
                                rel="noopener"
                                className="text-primary self-center text-sm font-semibold hover:underline"
                            >
                                {t('brand.catalogue.send')}
                            </a>
                        </div>
                        {copied && (
                            <div className="mt-2">
                                <Alert tone="success" title={t('brand.catalogue.copied')} />
                            </div>
                        )}
                    </Card>
                    <h2 className="text-fg mt-6 text-lg font-bold">{t('brand.catalogue.images')}</h2>
                    <ul className="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        {built.items.map((p) => (
                            <ProductImage key={p.id} p={p} kit={kit} business={business.name} />
                        ))}
                    </ul>
                </>
            )}
        </AppLayout>
    );
}
