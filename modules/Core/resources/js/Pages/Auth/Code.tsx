import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { OtpInput } from '@/components/ui/OtpInput';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

import { DemoCode } from '../../components/DemoCode';

interface CodeProps {
    phone: string;
    action: string;
    resendAction: string;
    demoCode?: string | null;
}

export default function Code({ phone, action, resendAction, demoCode }: CodeProps) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const form = useForm({ code: '' });

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        form.post(action, { preserveScroll: true, onError: () => form.reset('code') });
    };

    return (
        <AuthLayout title={t('auth.code.title')} description={t('auth.code.description', { phone })}>
            <Head title={t('auth.code.title')} />
            <DemoCode code={demoCode} />
            {flash.status && (
                <div className="mb-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div>
                    <p className="text-fg mb-2 text-sm font-semibold">{t('auth.code.label')}</p>
                    <OtpInput
                        value={form.data.code}
                        onChange={(value) => form.setData('code', value)}
                        label={t('auth.code.label')}
                        invalid={Boolean(form.errors.code)}
                        autoFocus
                    />
                    {form.errors.code && (
                        <p role="alert" className="text-danger-text mt-2 text-sm font-medium">
                            {form.errors.code}
                        </p>
                    )}
                </div>
                <Button type="submit" size="lg" block loading={form.processing} disabled={form.data.code.length < 6}>
                    {t('auth.code.verify')}
                </Button>
            </form>
            <div className="mt-6 flex flex-col items-center gap-3 text-sm">
                <button
                    type="button"
                    className="text-primary font-semibold hover:underline"
                    onClick={() => router.post(resendAction, {}, { preserveScroll: true })}
                >
                    {t('auth.code.resend')}
                </button>
                <Link href="/login" className="text-fg-muted hover:underline">
                    {t('auth.code.change_number')}
                </Link>
            </div>
        </AuthLayout>
    );
}
