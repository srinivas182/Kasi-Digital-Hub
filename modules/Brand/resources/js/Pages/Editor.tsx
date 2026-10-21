import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { DesignPreview, downloadPng, type Kit } from '../components/DesignPreview';

interface Props {
    business: { id: string; name: string };
    kit: Kit;
    template: string;
    limits: Record<string, number>;
    size: string;
    design: { id: number; title: string; fields: Record<string, string> } | null;
    prefill: Record<string, string>;
}

const csrf = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

/** The design editor (S30): live preview on top, plain fields below; PNG made on the phone, PDF for printing. */
export default function Editor({ business, kit, template, limits, size, design, prefill }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const svg = useRef<SVGSVGElement>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const form = useForm<{ title: string; fields: Record<string, string> }>({
        title: design?.title ?? t(`brand.template.${template}`),
        fields: Object.fromEntries(Object.keys(limits).map((k) => [k, design?.fields[k] ?? prefill[k] ?? ''])),
    });
    const set = (k: string, v: string) =>
        form.setData('fields', { ...form.data.fields, [k]: v.slice(0, limits[k] ?? 200) });
    const png = async () => {
        if (!svg.current) return;
        const ok = await downloadPng(svg.current, `${template}.png`);
        if (ok) {
            void fetch(`/brand/make/${template}/exported`, {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            });
            setNotice(kit.logo_kind === 'upload' ? t('brand.design.png_logo_note') : t('brand.design.png_done'));
        } else {
            setNotice(t('brand.design.png_failed'));
        }
    };
    // The encrypted XSRF cookie is accepted in the X-XSRF-TOKEN header (not as a form _token), so fetch the PDF.
    const pdf = async () => {
        const res = await fetch(`/brand/make/${template}/pdf`, {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                Accept: 'application/pdf',
            },
            body: JSON.stringify({ fields: form.data.fields }),
        });
        if (!res.ok) {
            setNotice(t('brand.design.pdf_failed'));
            return;
        }
        const url = URL.createObjectURL(await res.blob());
        const a = document.createElement('a');
        a.href = url;
        a.download = `${template}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
    };
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t(`brand.template.${template}`)} />
            <Link href="/brand" className="text-primary text-sm font-semibold hover:underline">
                ← {t('brand.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t(`brand.template.${template}`)}</h1>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {notice && (
                <div className="mt-3">
                    <Alert tone="info" title={notice} />
                </div>
            )}
            <div className="mt-4 grid gap-6 lg:grid-cols-2">
                <Card className="h-fit">
                    <div className="mx-auto max-w-sm">
                        <DesignPreview
                            ref={svg}
                            kit={kit}
                            template={template}
                            size={size}
                            fields={form.data.fields}
                            businessName={business.name}
                        />
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Button onClick={() => void png()}>{t('brand.design.download_png')}</Button>
                        <Button variant="secondary" onClick={() => void pdf()}>
                            {t('brand.design.download_pdf')}
                        </Button>
                    </div>
                </Card>
                <Card className="h-fit">
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({ ...d, design: design?.id ?? null }));
                            form.post(`/brand/make/${template}`, { preserveScroll: true });
                        }}
                    >
                        <Field label={t('brand.design.name')}>
                            <Input
                                maxLength={80}
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                            />
                        </Field>
                        {Object.entries(limits).map(([k, max]) => {
                            const value = form.data.fields[k] ?? '';
                            const hint =
                                k === 'items'
                                    ? t('brand.design.items_hint')
                                    : t('brand.design.left', { n: max - value.length });
                            return (
                                <Field key={k} label={t(`brand.field.${k}`)} hint={hint}>
                                    {k === 'until' ? (
                                        <Input type="date" value={value} onChange={(e) => set(k, e.target.value)} />
                                    ) : max > 80 ? (
                                        <Textarea
                                            rows={k === 'items' ? 8 : 3}
                                            value={value}
                                            onChange={(e) => set(k, e.target.value)}
                                        />
                                    ) : (
                                        <Input value={value} onChange={(e) => set(k, e.target.value)} />
                                    )}
                                </Field>
                            );
                        })}
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" variant="secondary" loading={form.processing}>
                                {t('brand.design.save')}
                            </Button>
                            {design && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => router.post(`/brand/designs/${design.id}/duplicate`)}
                                >
                                    {t('brand.design.duplicate')}
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
