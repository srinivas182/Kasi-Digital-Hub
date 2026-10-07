import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/form';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

import { DemoCode } from '../../components/DemoCode';

export default function TwoFactorChallenge({ demoCode }: { demoCode?: string | null }) {
    const { t } = useTranslation();
    const form = useForm({ code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/two-factor/challenge', { onError: () => form.reset('code') });
    };

    return (
        <AuthLayout
            title={t('auth.two_factor.challenge_title')}
            description={t('auth.two_factor.challenge_description')}
        >
            <Head title={t('auth.two_factor.challenge_title')} />
            <DemoCode code={demoCode} label="auth.two_factor.demo" />
            <form onSubmit={submit} className="flex flex-col gap-5">
                <Field label={t('auth.two_factor.code_or_backup')} error={form.errors.code} required>
                    <Input
                        autoComplete="one-time-code"
                        autoFocus
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                    />
                </Field>
                <Button type="submit" size="lg" block loading={form.processing}>
                    {t('auth.two_factor.verify')}
                </Button>
            </form>
            <Link href="/login" className="text-fg-muted mt-6 block text-center text-sm hover:underline">
                {t('auth.code.change_number')}
            </Link>
        </AuthLayout>
    );
}
