import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

export default function Phone({ expired, signedOut }: { expired: boolean; signedOut?: string | null }) {
    const { t } = useTranslation();
    const form = useForm({ phone: '', bot_token: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login');
    };

    return (
        <AuthLayout title={t('auth.phone.title')} description={t('auth.phone.description')}>
            <Head title={t('common.sign_in')} />
            {(expired || signedOut === 'idle') && (
                <div className="mb-4">
                    <Alert tone="info" title={t('auth.phone.expired')} />
                </div>
            )}
            {signedOut === 'device_signed_out' && (
                <div className="mb-4">
                    <Alert tone="info" title={t('auth.phone.device_signed_out')} />
                </div>
            )}
            <form onSubmit={submit} noValidate className="flex flex-col gap-6">
                <Field label={t('auth.phone.label')} hint={t('auth.phone.hint')} error={form.errors.phone}>
                    <PhoneInput value={form.data.phone} onChange={(_, raw) => form.setData('phone', raw)} autoFocus />
                </Field>
                <Button type="submit" size="lg" block loading={form.processing}>
                    {t('auth.phone.continue')}
                </Button>
            </form>
            <p className="text-fg-muted mt-6 text-center text-sm">{t('auth.phone.help')}</p>
        </AuthLayout>
    );
}
