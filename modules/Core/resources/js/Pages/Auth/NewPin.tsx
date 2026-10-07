import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

import { PinFields } from '../../components/PinFields';

export default function NewPin() {
    const { t } = useTranslation();
    const form = useForm({ pin: '', pin_confirmation: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login/new-pin', { onError: () => form.reset('pin', 'pin_confirmation') });
    };

    return (
        <AuthLayout title={t('auth.new_pin.title')}>
            <Head title={t('auth.new_pin.title')} />
            <form onSubmit={submit} className="flex flex-col gap-6">
                <PinFields
                    pin={form.data.pin}
                    confirmation={form.data.pin_confirmation}
                    onPin={(value) => form.setData('pin', value)}
                    onConfirmation={(value) => form.setData('pin_confirmation', value)}
                    error={form.errors.pin}
                />
                <Checkbox
                    label={t('auth.pin.remember')}
                    checked={form.data.remember}
                    onCheckedChange={(c) => form.setData('remember', c === true)}
                />
                <Button
                    type="submit"
                    size="lg"
                    block
                    loading={form.processing}
                    disabled={form.data.pin.length < 5 || form.data.pin !== form.data.pin_confirmation}
                >
                    {t('auth.new_pin.save')}
                </Button>
            </form>
        </AuthLayout>
    );
}
