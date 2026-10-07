import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/form';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

import { DemoCode } from '../../components/DemoCode';

export default function TwoFactorSetup({
    qrSvg,
    secret,
    demoCode,
}: {
    qrSvg: string;
    secret: string;
    demoCode?: string | null;
}) {
    const { t } = useTranslation();
    const form = useForm({ code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/two-factor/setup', { onError: () => form.reset('code') });
    };

    return (
        <AuthLayout title={t('auth.two_factor.setup_title')} description={t('auth.two_factor.setup_description')}>
            <Head title={t('auth.two_factor.setup_title')} />
            <DemoCode code={demoCode} label="auth.two_factor.demo" />
            <div className="flex flex-col items-center gap-3">
                {/* QR code generated server-side as SVG from our own data (no external service). */}
                <div
                    className="rounded-card bg-white p-3"
                    role="img"
                    aria-label="QR code for your authenticator app"
                    dangerouslySetInnerHTML={{ __html: qrSvg }}
                />
                <p className="text-fg-muted text-sm">{t('auth.two_factor.manual')}</p>
                <code className="bg-surface-muted text-fg rounded-control px-3 py-2 font-mono text-sm tracking-wider">
                    {secret}
                </code>
            </div>
            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <Field label={t('auth.two_factor.code')} error={form.errors.code} required>
                    <Input
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                    />
                </Field>
                <Button type="submit" size="lg" block loading={form.processing}>
                    {t('auth.two_factor.confirm')}
                </Button>
            </form>
        </AuthLayout>
    );
}
