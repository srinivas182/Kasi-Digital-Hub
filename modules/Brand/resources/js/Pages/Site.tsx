import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    business: { id: string; name: string };
    site: {
        name: string | null;
        about: string | null;
        services: { name: string; price?: string }[];
        use_market: boolean;
        status: string;
        held_reason: string | null;
        can_rename: boolean;
    } | null;
    url: string | null;
    suggested: string | null;
    domain: string;
    idVerified: boolean;
    photos: { id: number; url: string; alt: string }[];
    stats: { week: string; views: number; whatsapp: number; orders: number }[];
    maxPhotos: number;
    maxServices: number;
}

/** The business website editor (S31). */
export default function SitePage({
    site,
    url,
    suggested,
    domain,
    idVerified,
    photos,
    stats,
    maxPhotos,
    maxServices,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({
        name: site?.name ?? suggested ?? '',
        about: site?.about ?? '',
        use_market: site?.use_market ?? true,
        services: site?.services ?? [],
    });
    const photo = useForm<{ photo: File | null; alt: string }>({ photo: null, alt: '' });
    const [check, setCheck] = useState<{ name: string; ok: boolean; message: string } | null>(null);
    useEffect(() => {
        if (!form.data.name || form.data.name === site?.name) {
            return;
        }
        const timer = setTimeout(() => {
            void fetch(`/brand/site/check?name=${encodeURIComponent(form.data.name)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((j: { ok: boolean; message: string }) => setCheck({ name: form.data.name, ...j }));
        }, 400);
        return () => clearTimeout(timer);
    }, [form.data.name, site?.name]);
    const status = site?.status ?? 'draft';
    const total = stats.reduce(
        (a, s) => ({ views: a.views + s.views, whatsapp: a.whatsapp + s.whatsapp, orders: a.orders + s.orders }),
        { views: 0, whatsapp: 0, orders: 0 },
    );
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('brand.site.title')} />
            <Link href="/brand" className="text-primary text-sm font-semibold hover:underline">
                ← {t('brand.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                <h1 className="text-fg text-2xl font-bold">{t('brand.site.title')}</h1>
                <Badge tone={status === 'published' ? 'success' : status === 'draft' ? 'neutral' : 'warning'}>
                    {t(`brand.site.status.${status}`)}
                </Badge>
            </div>
            {url && status === 'published' && (
                <p className="mt-1">
                    <a
                        href={url}
                        className="text-primary font-semibold break-all hover:underline"
                        rel="noopener"
                        target="_blank"
                    >
                        {url}
                    </a>
                </p>
            )}
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.brand && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.brand} />
                </div>
            )}
            {site?.held_reason && (
                <div className="mt-3">
                    <Alert tone="warning" title={site.held_reason} />
                </div>
            )}
            {!idVerified && (
                <div className="mt-3">
                    <Alert tone="info" title={t('brand.site.needs_id')} />
                </div>
            )}
            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <Card>
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/brand/site', { preserveScroll: true });
                        }}
                    >
                        <Field label={t('brand.site.address')} hint={t('brand.site.address_hint', { domain })}>
                            <Input
                                value={form.data.name}
                                disabled={!!site?.name && !site.can_rename}
                                onChange={(e) =>
                                    form.setData('name', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))
                                }
                            />
                        </Field>
                        {check && (
                            <p className={`text-sm ${check.ok ? 'text-success-text' : 'text-danger-text'}`}>
                                {check.message}
                            </p>
                        )}
                        <Field label={t('brand.site.about')}>
                            <Textarea
                                rows={4}
                                maxLength={600}
                                value={form.data.about}
                                onChange={(e) => form.setData('about', e.target.value)}
                            />
                        </Field>
                        <Checkbox
                            label={t('brand.site.use_market')}
                            checked={form.data.use_market}
                            onCheckedChange={(c) => form.setData('use_market', c === true)}
                        />
                        {!form.data.use_market && (
                            <fieldset className="flex flex-col gap-2">
                                <legend className="text-fg text-sm font-semibold">{t('brand.site.services')}</legend>
                                {form.data.services.map((s, i) => (
                                    <div key={i} className="grid grid-cols-[1fr_7rem] gap-2">
                                        <Input
                                            aria-label={t('brand.site.service_name')}
                                            value={s.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'services',
                                                    form.data.services.map((x, j) =>
                                                        j === i ? { ...x, name: e.target.value } : x,
                                                    ),
                                                )
                                            }
                                        />
                                        <Input
                                            aria-label={t('brand.site.service_price')}
                                            value={s.price ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'services',
                                                    form.data.services.map((x, j) =>
                                                        j === i ? { ...x, price: e.target.value } : x,
                                                    ),
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                                {form.data.services.length < maxServices && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            form.setData('services', [...form.data.services, { name: '', price: '' }])
                                        }
                                    >
                                        {t('brand.site.add_service')}
                                    </Button>
                                )}
                            </fieldset>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" variant="secondary" loading={form.processing}>
                                {t('brand.site.save')}
                            </Button>
                            {status !== 'published' ? (
                                <Button
                                    type="button"
                                    disabled={!idVerified || !site?.name}
                                    onClick={() => router.post('/brand/site/publish', {}, { preserveScroll: true })}
                                >
                                    {t('brand.site.publish')}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        router.post(
                                            '/brand/site/publish',
                                            { unpublish: true },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('brand.site.unpublish')}
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>
                <div className="flex flex-col gap-4">
                    <Card>
                        <CardTitle>{t('brand.site.photos', { max: maxPhotos })}</CardTitle>
                        <ul className="mt-2 grid grid-cols-3 gap-2">
                            {photos.map((p) => (
                                <li key={p.id} className="flex flex-col gap-1">
                                    <img
                                        src={p.url}
                                        alt={p.alt}
                                        className="h-20 w-full rounded object-cover"
                                        loading="lazy"
                                    />
                                    <button
                                        type="button"
                                        className="text-danger-text text-xs underline"
                                        onClick={() =>
                                            router.delete(`/brand/site/photos/${p.id}`, { preserveScroll: true })
                                        }
                                    >
                                        {t('brand.site.remove')}
                                    </button>
                                </li>
                            ))}
                        </ul>
                        {photos.length < maxPhotos && (
                            <form
                                className="mt-2 flex flex-col gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    photo.post('/brand/site/photos', {
                                        forceFormData: true,
                                        preserveScroll: true,
                                        onSuccess: () => photo.reset(),
                                    });
                                }}
                            >
                                <Field label={t('brand.site.photo_file')}>
                                    <FileInput
                                        file={photo.data.photo}
                                        onChange={(f) => photo.setData('photo', f)}
                                        chooseLabel={t('documents.file')}
                                        photoLabel={t('documents.take_photo')}
                                        accept="image/*"
                                    />
                                </Field>
                                <Field label={t('brand.site.alt')} hint={t('brand.site.alt_hint')}>
                                    <Input
                                        maxLength={120}
                                        value={photo.data.alt}
                                        onChange={(e) => photo.setData('alt', e.target.value)}
                                    />
                                </Field>
                                <div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="secondary"
                                        disabled={!photo.data.photo || !photo.data.alt.trim()}
                                    >
                                        {t('brand.site.add_photo')}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </Card>
                    <Card>
                        <CardTitle>{t('brand.site.stats')}</CardTitle>
                        <p className="text-fg mt-1 text-sm">
                            {t('brand.site.stats_total', {
                                views: total.views,
                                whatsapp: total.whatsapp,
                                orders: total.orders,
                            })}
                        </p>
                        <p className="text-fg-muted mt-1 text-xs">{t('brand.site.stats_private')}</p>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
