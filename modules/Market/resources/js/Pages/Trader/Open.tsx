import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

/** Open a shop for one of your KasiStart businesses (S24). */
export default function OpenShop({
    businesses,
}: {
    businesses: { id: string; name: string; blocker: string | null }[];
}) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const ready = businesses.filter((b) => b.blocker === null);
    const form = useForm({
        business_id: ready[0]?.id ?? '',
        name: ready[0]?.name ?? '',
        description: '',
        area: '',
        whatsapp: '',
        whatsapp_public: false,
    });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.open.title')} />
            <div className="mx-auto max-w-xl">
                <h1 className="text-fg text-2xl font-bold">{t('market.open.title')}</h1>
                <p className="text-fg-muted mt-1">{t('market.open.lead')}</p>
                {errors?.shop && (
                    <div className="mt-3">
                        <Alert tone="danger" title={errors.shop} />
                    </div>
                )}
                {businesses.length === 0 && (
                    <Card className="mt-4">
                        <p className="text-fg">{t('market.open.need_business')}</p>
                        <Link href="/start" className="text-primary mt-2 inline-block font-semibold hover:underline">
                            {t('market.open.go_start')}
                        </Link>
                    </Card>
                )}
                {businesses
                    .filter((b) => b.blocker !== null)
                    .map((b) => (
                        <div key={b.id} className="mt-3">
                            <Alert tone="info" title={`${b.name}: ${t(b.blocker ?? '')}`} />
                        </div>
                    ))}
                {ready.length > 0 && (
                    <Card className="mt-4">
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/market/shop');
                            }}
                        >
                            {ready.length > 1 && (
                                <Field label={t('market.open.business')}>
                                    <Select
                                        value={form.data.business_id}
                                        onChange={(e) => form.setData('business_id', e.target.value)}
                                    >
                                        {ready.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}
                            <Field label={t('market.open.name')} error={form.errors.name}>
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            </Field>
                            <Field label={t('market.open.description')}>
                                <Textarea
                                    rows={3}
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                            </Field>
                            <Field label={t('market.open.area')} hint={t('market.open.area_hint')}>
                                <Input value={form.data.area} onChange={(e) => form.setData('area', e.target.value)} />
                            </Field>
                            <Field
                                label={t('market.open.whatsapp')}
                                hint={t('market.open.whatsapp_hint')}
                                error={form.errors.whatsapp}
                            >
                                <Input
                                    type="tel"
                                    inputMode="tel"
                                    value={form.data.whatsapp}
                                    onChange={(e) => form.setData('whatsapp', e.target.value)}
                                />
                            </Field>
                            <Checkbox
                                label={t('market.open.whatsapp_public')}
                                checked={form.data.whatsapp_public}
                                onCheckedChange={(c) => form.setData('whatsapp_public', c === true)}
                            />
                            <div>
                                <Button type="submit" loading={form.processing} disabled={!form.data.name.trim()}>
                                    {t('market.open.submit')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
