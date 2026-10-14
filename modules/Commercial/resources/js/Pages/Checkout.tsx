import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    product: { key: string; name: string; unit: string };
    price: string;
    priceCents: number;
    organisation: string | null;
    organisationId: string | null;
    hub: string | null;
    attribution: string;
    refHub: string | null;
}

export default function Checkout({
    product,
    price,
    priceCents,
    organisation,
    organisationId,
    hub,
    attribution,
    refHub,
}: Props) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const form = useForm({ quantity: '1', organisation: organisationId ?? '', hub: refHub ?? '' });
    const quantity = Math.max(1, Number(form.data.quantity) || 1);
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('commercial.checkout.title')} />
            <div className="mx-auto max-w-lg">
                <h1 className="text-fg text-2xl font-bold">{t('commercial.checkout.title')}</h1>
                {errors?.checkout && (
                    <div className="mt-3">
                        <Alert tone="danger" title={errors.checkout} />
                    </div>
                )}
                <Card className="mt-4">
                    <p className="text-fg text-lg font-semibold">{product.name}</p>
                    {organisation && (
                        <p className="text-fg-muted text-sm">{t('commercial.checkout.for', { name: organisation })}</p>
                    )}
                    <p className="text-fg mt-2">
                        {t('commercial.checkout.price', { price, unit: t(`commercial.unit.${product.unit}`) })}
                    </p>
                    <form
                        className="mt-4 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({ ...d, quantity: Number(d.quantity), hub: d.hub || null }));
                            form.post(`/commercial/buy/${product.key}`);
                        }}
                    >
                        <Field label={t('commercial.checkout.quantity')} error={form.errors.quantity}>
                            <Input
                                type="number"
                                min={1}
                                max={20}
                                value={form.data.quantity}
                                onChange={(e) => form.setData('quantity', e.target.value)}
                            />
                        </Field>
                        <p className="text-fg text-lg font-bold">
                            {t('commercial.checkout.total', { total: formatMoney(priceCents * quantity) })}
                        </p>
                        <p className="text-fg-muted text-sm">
                            {hub ? t('commercial.checkout.hub', { hub }) : t('commercial.checkout.national')}
                        </p>
                        <Button type="submit" loading={form.processing}>
                            {t('commercial.checkout.pay')}
                        </Button>
                        <p className="text-fg-muted text-xs">
                            {t('commercial.checkout.secure', {
                                attribution: t(`commercial.attribution.${attribution}`),
                            })}
                        </p>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
