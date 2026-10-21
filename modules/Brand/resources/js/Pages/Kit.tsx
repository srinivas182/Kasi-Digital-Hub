import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card, CardTitle } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type Kit } from '../components/DesignPreview';

interface Props {
    business: { id: string; name: string };
    kit: Kit & {
        logo_shape: string;
        logo_text: string;
        contact: Record<string, boolean>;
        contrast: number;
        suggested_primary: string;
    };
    palettes: Record<string, [string, string]>;
    shapes: string[];
    lettering: string[];
    ideas: string[];
}

/** WCAG contrast ratio, as on the server. */
function contrast(a: string, b: string): number {
    const lum = (hex: string) => {
        const c = [1, 3, 5]
            .map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
            .map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
        return 0.2126 * (c[0] ?? 0) + 0.7152 * (c[1] ?? 0) + 0.0722 * (c[2] ?? 0);
    };
    const [l1, l2] = [lum(a), lum(b)];
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

/** The brand kit editor (S30). */
export default function KitPage({ business, kit, palettes, shapes, lettering, ideas }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const form = useForm({
        logo_shape: kit.logo_shape,
        logo_text: kit.logo_text,
        primary: kit.primary,
        secondary: kit.secondary,
        lettering: kit.lettering,
        tagline: kit.tagline ?? '',
        contact: kit.contact,
    });
    const logo = useForm<{ logo: File | null; own: boolean }>({ logo: null, own: false });
    const valid = /^#[0-9A-Fa-f]{6}$/.test(form.data.primary);
    const ratio = valid ? Math.max(contrast(form.data.primary, '#FFFFFF'), contrast(form.data.primary, '#000000')) : 0;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('brand.kit.title')} />
            <Link href="/brand" className="text-primary text-sm font-semibold hover:underline">
                ← {t('brand.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('brand.kit.title')}</h1>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-4 grid gap-6 lg:grid-cols-2">
                <Card>
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/brand/kit', { preserveScroll: true });
                        }}
                    >
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('brand.kit.palette')}</legend>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {Object.entries(palettes).map(([key, [p, s]]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        aria-label={t(`brand.palette.${key}`)}
                                        aria-pressed={form.data.primary === p && form.data.secondary === s}
                                        className="border-line flex overflow-hidden rounded border aria-pressed:ring-2"
                                        onClick={() => form.setData((d) => ({ ...d, primary: p, secondary: s }))}
                                    >
                                        <span className="block size-8" style={{ background: p }} />
                                        <span className="block size-8" style={{ background: s }} />
                                    </button>
                                ))}
                            </div>
                        </fieldset>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('brand.kit.primary')}>
                                <Input
                                    type="color"
                                    value={form.data.primary}
                                    onChange={(e) => form.setData('primary', e.target.value.toUpperCase())}
                                />
                            </Field>
                            <Field label={t('brand.kit.secondary')}>
                                <Input
                                    type="color"
                                    value={form.data.secondary}
                                    onChange={(e) => form.setData('secondary', e.target.value.toUpperCase())}
                                />
                            </Field>
                        </div>
                        {valid && ratio < 4.5 && (
                            <Alert tone="warning" title={t('brand.kit.contrast_low', { ratio: ratio.toFixed(1) })} />
                        )}
                        {valid && ratio < 4.5 && (
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() => form.setData('primary', kit.suggested_primary)}
                            >
                                {t('brand.kit.use_suggested')}
                            </Button>
                        )}
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('brand.kit.shape')}>
                                <Select
                                    value={form.data.logo_shape}
                                    onChange={(e) => form.setData('logo_shape', e.target.value)}
                                >
                                    {shapes.map((s) => (
                                        <option key={s} value={s}>
                                            {t(`brand.shape.${s}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('brand.kit.logo_text')} hint={t('brand.kit.logo_text_hint')}>
                                <Input
                                    maxLength={24}
                                    value={form.data.logo_text}
                                    onChange={(e) => form.setData('logo_text', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Field label={t('brand.kit.lettering')}>
                            <Select
                                value={form.data.lettering}
                                onChange={(e) => form.setData('lettering', e.target.value)}
                            >
                                {lettering.map((l) => (
                                    <option key={l} value={l}>
                                        {t(`brand.lettering.${l}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('brand.kit.tagline')} hint={t('brand.kit.tagline_hint')}>
                            <Input
                                maxLength={60}
                                value={form.data.tagline}
                                onChange={(e) => form.setData('tagline', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => router.post('/brand/kit/taglines', {}, { preserveScroll: true })}
                            >
                                {t('brand.kit.ideas')}
                            </Button>
                            {ideas.length > 0 && (
                                <ul className="mt-2 flex flex-col gap-1 text-sm">
                                    <li className="text-fg-muted">{t('brand.kit.ideas_note')}</li>
                                    {ideas.map((idea) => (
                                        <li key={idea}>
                                            <button
                                                type="button"
                                                className="text-primary text-left underline"
                                                onClick={() => form.setData('tagline', idea)}
                                            >
                                                {idea}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('brand.kit.show')}</legend>
                            {['whatsapp', 'area', 'hours'].map((c) => (
                                <Checkbox
                                    key={c}
                                    label={t(`brand.contact.${c}`)}
                                    checked={Boolean(form.data.contact[c])}
                                    onCheckedChange={(v) =>
                                        form.setData('contact', { ...form.data.contact, [c]: v === true })
                                    }
                                />
                            ))}
                        </fieldset>
                        <div>
                            <Button type="submit" loading={form.processing} disabled={!valid}>
                                {t('brand.kit.save')}
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('brand.kit.logo')}</CardTitle>
                    {kit.logo_kind === 'upload' && kit.logo_url ? (
                        <img
                            src={kit.logo_url}
                            alt={t('brand.kit.logo_alt', { business: business.name })}
                            width={120}
                            height={120}
                            className="mt-2 size-30 object-contain"
                        />
                    ) : (
                        <img
                            src={`data:image/svg+xml;utf8,${encodeURIComponent(kit.logo_svg)}`}
                            alt={t('brand.kit.logo_alt', { business: business.name })}
                            width={120}
                            height={120}
                            className="mt-2"
                        />
                    )}
                    <form
                        className="mt-3 flex flex-col gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            logo.post('/brand/kit/logo', { forceFormData: true, preserveScroll: true });
                        }}
                    >
                        <Field label={t('brand.kit.upload')} error={logo.errors.logo}>
                            <FileInput
                                file={logo.data.logo}
                                onChange={(f) => logo.setData('logo', f)}
                                chooseLabel={t('documents.file')}
                                photoLabel={t('documents.take_photo')}
                                accept="image/*"
                            />
                        </Field>
                        <Checkbox
                            label={t('brand.kit.own')}
                            checked={logo.data.own}
                            onCheckedChange={(v) => logo.setData('own', v === true)}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                variant="secondary"
                                disabled={!logo.data.logo || !logo.data.own}
                            >
                                {t('brand.kit.upload_button')}
                            </Button>
                            {kit.logo_kind === 'upload' && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        router.post('/brand/kit/logo', { use_text: true }, { preserveScroll: true })
                                    }
                                >
                                    {t('brand.kit.use_text_logo')}
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
