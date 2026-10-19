import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Option {
    label: string;
    price_cents: number | null;
    stock: number | null;
}

interface Props {
    product: {
        id: string;
        title: string;
        description: string | null;
        category_id: number;
        price_cents: number;
        unit: string;
        stock_type: string;
        stock: number | null;
        status: string;
        options: Option[];
        photoList: { id: number; url: string; alt: string }[];
    } | null;
    categories: { id: number; slug: string }[];
    units: string[];
    stockTypes: string[];
}

/** Add or edit a product. On a phone the quick path is: photo, title, price, publish. */
export default function ProductForm({ product, categories, units, stockTypes }: Props) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const form = useForm<{
        title: string;
        description: string;
        category_id: string;
        price: string;
        unit: string;
        stock_type: string;
        stock: string;
        options: { label: string; price: string; stock: string }[];
        photo: File | null;
        photo_alt: string;
        publish: boolean;
    }>({
        title: product?.title ?? '',
        description: product?.description ?? '',
        category_id: String(product?.category_id ?? categories[0]?.id ?? ''),
        price: product ? (product.price_cents / 100).toFixed(2) : '',
        unit: product?.unit ?? 'each',
        stock_type: product?.stock_type ?? 'quantity',
        stock: String(product?.stock ?? 1),
        options: (product?.options ?? []).map((o) => ({
            label: o.label,
            price: o.price_cents ? (o.price_cents / 100).toFixed(2) : '',
            stock: o.stock === null ? '' : String(o.stock),
        })),
        photo: null,
        photo_alt: '',
        publish: false,
    });
    const cents = (v: string) => (v === '' ? null : Math.round(Number(v) * 100));
    const submit = (publish: boolean) => {
        form.transform((d) => ({
            title: d.title,
            description: d.description || null,
            category_id: Number(d.category_id),
            price_cents: cents(d.price),
            unit: d.unit,
            stock_type: d.stock_type,
            stock: d.stock_type === 'quantity' ? Number(d.stock || 0) : null,
            options: d.options
                .filter((o) => o.label.trim())
                .map((o) => ({
                    label: o.label,
                    price_cents: cents(o.price),
                    stock: o.stock === '' ? null : Number(o.stock),
                })),
            photo: d.photo,
            photo_alt: d.photo ? d.photo_alt : null,
            publish,
        }));
        form.post(product ? `/market/shop/products/${product.id}` : '/market/shop/products', { forceFormData: true });
    };
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={product ? product.title : t('market.form.new')} />
            <Link href="/market/shop" className="text-primary text-sm font-semibold hover:underline">
                ← {t('market.dash.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{product ? product.title : t('market.form.new')}</h1>
            {(errors?.product || errors?.photo) && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.product ?? errors.photo ?? ''} />
                </div>
            )}
            <form
                className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]"
                onSubmit={(e) => {
                    e.preventDefault();
                    submit(true);
                }}
            >
                <Card className="flex flex-col gap-3">
                    <Field label={t('market.form.photo')} hint={t('market.form.photo_hint')} error={form.errors.photo}>
                        <FileInput
                            file={form.data.photo}
                            onChange={(f) => form.setData('photo', f)}
                            chooseLabel={t('documents.file')}
                            photoLabel={t('documents.take_photo')}
                            accept="image/*"
                        />
                    </Field>
                    {form.data.photo && (
                        <Field
                            label={t('market.form.alt')}
                            hint={t('market.form.alt_hint')}
                            error={form.errors.photo_alt}
                        >
                            <Input
                                value={form.data.photo_alt}
                                onChange={(e) => form.setData('photo_alt', e.target.value)}
                            />
                        </Field>
                    )}
                    <Field label={t('market.form.title')} error={form.errors.title}>
                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    </Field>
                    <Field
                        label={t('market.form.price')}
                        error={(form.errors as Record<string, string | undefined>).price_cents}
                    >
                        <Input
                            type="number"
                            min={0}
                            step="0.01"
                            inputMode="decimal"
                            value={form.data.price}
                            onChange={(e) => form.setData('price', e.target.value)}
                        />
                    </Field>
                    <Field label={t('market.category_label')}>
                        <Select
                            value={form.data.category_id}
                            onChange={(e) => form.setData('category_id', e.target.value)}
                        >
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {t(`market.category.${c.slug}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('market.form.description')}>
                        <Textarea
                            rows={4}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Field>
                    {product && product.photoList.length > 0 && (
                        <ul className="flex flex-wrap gap-2">
                            {product.photoList.map((ph) => (
                                <li key={ph.id} className="relative">
                                    <img
                                        src={ph.url}
                                        alt={ph.alt}
                                        width={96}
                                        height={72}
                                        className="h-18 w-24 rounded object-cover"
                                    />
                                    <button
                                        type="button"
                                        aria-label={t('market.form.remove_photo')}
                                        className="bg-surface absolute top-1 right-1 rounded p-1"
                                        onClick={() =>
                                            router.delete(`/market/shop/products/${product.id}/photos/${ph.id}`, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <Trash2 className="size-4" aria-hidden />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
                <Card className="flex h-fit flex-col gap-3">
                    <Field label={t('market.form.unit')}>
                        <Select value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)}>
                            {units.map((u) => (
                                <option key={u} value={u}>
                                    {t(`market.unit.${u}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('market.form.stock_type')}>
                        <Select
                            value={form.data.stock_type}
                            onChange={(e) => form.setData('stock_type', e.target.value)}
                        >
                            {stockTypes.map((s) => (
                                <option key={s} value={s}>
                                    {t(`market.stock_type.${s}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {form.data.stock_type === 'quantity' && (
                        <Field label={t('market.form.stock')}>
                            <Input
                                type="number"
                                min={0}
                                inputMode="numeric"
                                value={form.data.stock}
                                onChange={(e) => form.setData('stock', e.target.value)}
                            />
                        </Field>
                    )}
                    <fieldset>
                        <legend className="text-fg text-sm font-semibold">{t('market.form.options')}</legend>
                        {form.data.options.map((o, i) => (
                            <div key={i} className="mt-2 grid grid-cols-3 gap-2">
                                <Input
                                    aria-label={t('market.form.option_label')}
                                    placeholder={t('market.form.option_label')}
                                    value={o.label}
                                    onChange={(e) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((x, j) =>
                                                j === i ? { ...x, label: e.target.value } : x,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label={t('market.form.option_price')}
                                    placeholder={t('market.form.option_price')}
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={o.price}
                                    onChange={(e) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((x, j) =>
                                                j === i ? { ...x, price: e.target.value } : x,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label={t('market.form.option_stock')}
                                    placeholder={t('market.form.option_stock')}
                                    type="number"
                                    min={0}
                                    value={o.stock}
                                    onChange={(e) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((x, j) =>
                                                j === i ? { ...x, stock: e.target.value } : x,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        ))}
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="mt-2"
                            onClick={() =>
                                form.setData('options', [...form.data.options, { label: '', price: '', stock: '' }])
                            }
                        >
                            {t('market.form.add_option')}
                        </Button>
                    </fieldset>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            loading={form.processing}
                            disabled={
                                !form.data.title.trim() ||
                                !form.data.price ||
                                (form.data.photo !== null && !form.data.photo_alt.trim())
                            }
                        >
                            {t('market.form.publish')}
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => submit(false)}
                            disabled={form.processing || !form.data.title.trim() || !form.data.price}
                        >
                            {t('market.form.save_draft')}
                        </Button>
                    </div>
                    <p className="text-fg-muted text-xs">{t('market.form.safety_note')}</p>
                </Card>
            </form>
        </AppLayout>
    );
}
