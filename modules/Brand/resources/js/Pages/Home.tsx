import { Head, Link, usePage } from '@inertiajs/react';

import { Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type Kit } from '../components/DesignPreview';

interface Props {
    business: { id: string; name: string };
    kit: Kit & { saved: boolean };
    templates: string[];
    recent: { id: number; template: string; title: string }[];
}

/** KasiBrand home (S30): the brand at a glance and "make something". */
export default function BrandHome({ business, kit, templates, recent }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('brand.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('brand.title')}</h1>
            <Card className="mt-4 flex flex-wrap items-center gap-4">
                {kit.logo_kind === 'upload' && kit.logo_url ? (
                    <img
                        src={kit.logo_url}
                        alt={t('brand.kit.logo_alt', { business: business.name })}
                        width={72}
                        height={72}
                        className="size-18 rounded object-contain"
                    />
                ) : (
                    <img
                        src={`data:image/svg+xml;utf8,${encodeURIComponent(kit.logo_svg)}`}
                        alt={t('brand.kit.logo_alt', { business: business.name })}
                        width={72}
                        height={72}
                        className="size-18"
                    />
                )}
                <div className="flex-1">
                    <p className="text-fg text-lg font-bold">{business.name}</p>
                    {kit.tagline && <p className="text-fg-muted">{kit.tagline}</p>}
                    <p className="mt-1 flex gap-1" aria-hidden>
                        <span className="size-6 rounded" style={{ background: kit.primary }} />
                        <span className="size-6 rounded" style={{ background: kit.secondary }} />
                    </p>
                </div>
                <Link href="/brand/kit" className="text-primary font-semibold hover:underline">
                    {kit.saved ? t('brand.kit.edit') : t('brand.kit.start')}
                </Link>
            </Card>
            <h2 className="text-fg mt-6 text-lg font-bold">{t('brand.make')}</h2>
            <ul className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {templates.map((tpl) => (
                    <li key={tpl}>
                        <Link
                            href={`/brand/make/${tpl}`}
                            className="border-line bg-surface hover:bg-surface-muted block rounded-lg border p-3"
                        >
                            <span className="text-fg block font-semibold">{t(`brand.template.${tpl}`)}</span>
                            <span className="text-fg-muted text-sm">{t(`brand.template_hint.${tpl}`)}</span>
                        </Link>
                    </li>
                ))}
            </ul>
            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardTitle>{t('brand.recent')}</CardTitle>
                    <ul className="mt-2 text-sm">
                        {recent.map((d) => (
                            <li key={d.id}>
                                <Link
                                    href={`/brand/make/${d.template}/${d.id}`}
                                    className="text-primary hover:underline"
                                >
                                    {d.title}
                                </Link>
                            </li>
                        ))}
                        {recent.length === 0 && <li className="text-fg-muted">{t('brand.none_yet')}</li>}
                    </ul>
                </Card>
                <Card>
                    <CardTitle>{t('brand.catalogue.title')}</CardTitle>
                    <p className="text-fg-muted mt-1 text-sm">{t('brand.catalogue.lead')}</p>
                    <Link
                        href="/brand/catalogue"
                        className="text-primary mt-2 inline-block font-semibold hover:underline"
                    >
                        {t('brand.catalogue.open')}
                    </Link>
                </Card>
            </div>
        </AppLayout>
    );
}
