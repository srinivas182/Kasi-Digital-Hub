import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { OtpInput } from '@/components/ui/OtpInput';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

interface PinProps {
    phone: string;
    askRemember: boolean;
    lockedMinutes: number | null;
}

export default function Pin({ phone, askRemember, lockedMinutes }: PinProps) {
    const { t } = useTranslation();
    const form = useForm({ pin: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login/pin', { onError: () => form.reset('pin') });
    };

    return (
        <AuthLayout title={t('auth.pin.title')} description={t('auth.pin.description', { phone })}>
            <Head title={t('auth.pin.title')} />
            {lockedMinutes !== null && (
                <div className="mb-4">
                    <Alert tone="warning" title={t('auth.pin.locked', { minutes: lockedMinutes })} />
                </div>
            )}
            <form onSubmit={submit} className="flex flex-col gap-6">
                <div>
                    <p className="text-fg mb-2 text-sm font-semibold">{t('auth.pin.label')}</p>
                    <OtpInput
                        length={5}
                        secret
                        autoFocus
                        value={form.data.pin}
                        onChange={(value) => form.setData('pin', value)}
                        label={t('auth.pin.label')}
                        invalid={Boolean(form.errors.pin)}
                    />
                    {form.errors.pin && (
                        <p role="alert" className="text-danger-text mt-2 text-sm font-medium">
                            {form.errors.pin}
                        </p>
                    )}
                </div>
                {askRemember && (
                    <div>
                        <Checkbox
                            label={t('auth.pin.remember')}
                            checked={form.data.remember}
                            onCheckedChange={(checked) => form.setData('remember', checked === true)}
                        />
                        <p className="text-fg-muted mt-1 pl-9 text-sm">{t('auth.pin.remember_hint')}</p>
                    </div>
                )}
                <Button type="submit" size="lg" block loading={form.processing} disabled={form.data.pin.length < 5}>
                    {t('auth.pin.sign_in')}
                </Button>
            </form>
            <div className="mt-6 flex flex-col items-center gap-3 text-sm">
                <button
                    type="button"
                    className="text-primary font-semibold hover:underline"
                    onClick={() => router.post('/login/forgot')}
                >
                    {t('auth.pin.forgot')}
                </button>
                <Link href="/login" className="text-fg-muted hover:underline">
                    {t('auth.code.change_number')}
                </Link>
            </div>
        </AuthLayout>
    );
}
