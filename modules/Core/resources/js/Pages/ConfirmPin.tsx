import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

/** S23: staff confirm their PIN again before a sensitive action. */
export default function ConfirmPin() {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const form = useForm({ pin: '' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('auth.confirm_pin.title')} />
            <div className="mx-auto max-w-sm">
                <h1 className="text-fg text-2xl font-bold">{t('auth.confirm_pin.title')}</h1>
                <p className="text-fg-muted mt-1">{t('auth.confirm_pin.lead')}</p>
                {flash.status && (
                    <div className="mt-3">
                        <Alert tone="info" title={flash.status} />
                    </div>
                )}
                <Card className="mt-4">
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/confirm-pin', { onFinish: () => form.reset('pin') });
                        }}
                    >
                        <Field label={t('auth.pin.label')} error={form.errors.pin}>
                            <Input
                                type="password"
                                inputMode="numeric"
                                autoComplete="current-password"
                                maxLength={12}
                                value={form.data.pin}
                                onChange={(e) => form.setData('pin', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button type="submit" loading={form.processing} disabled={form.data.pin.length < 5}>
                                {t('auth.confirm_pin.button')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
