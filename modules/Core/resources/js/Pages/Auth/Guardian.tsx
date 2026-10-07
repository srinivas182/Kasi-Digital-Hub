import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Field, Input, Select } from '@/components/ui/form';
import { OtpInput } from '@/components/ui/OtpInput';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

import { DemoCode } from '../../components/DemoCode';

interface GuardianProps {
    name: string;
    relationships: string[];
    codeSent: boolean;
    guardianPhone: string | null;
    demoCode?: string | null;
}

export default function Guardian({ name, relationships, codeSent, guardianPhone, demoCode }: GuardianProps) {
    const { t } = useTranslation();
    const details = useForm({ guardian_name: '', guardian_phone: '', relationship: 'parent' });
    const code = useForm({ code: '' });

    const sendCode = (event: FormEvent) => {
        event.preventDefault();
        details.post('/signup/guardian');
    };

    const confirm = (event: FormEvent) => {
        event.preventDefault();
        code.post('/signup/guardian/code', { onError: () => code.reset('code') });
    };

    if (codeSent) {
        return (
            <AuthLayout
                title={t('auth.guardian.code_title')}
                description={t('auth.guardian.code_description', { phone: guardianPhone ?? '' })}
            >
                <Head title={t('auth.guardian.code_title')} />
                <DemoCode code={demoCode} />
                <form onSubmit={confirm} className="flex flex-col gap-6">
                    <div>
                        <OtpInput
                            value={code.data.code}
                            onChange={(value) => code.setData('code', value)}
                            invalid={Boolean(code.errors.code)}
                            autoFocus
                        />
                        {code.errors.code && (
                            <p role="alert" className="text-danger-text mt-2 text-sm font-medium">
                                {code.errors.code}
                            </p>
                        )}
                    </div>
                    <Button
                        type="submit"
                        size="lg"
                        block
                        loading={code.processing}
                        disabled={code.data.code.length < 6}
                    >
                        {t('auth.guardian.confirm')}
                    </Button>
                </form>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={t('auth.guardian.title')} description={t('auth.guardian.description', { name })}>
            <Head title={t('auth.guardian.title')} />
            <form onSubmit={sendCode} className="flex flex-col gap-5" noValidate>
                <Field label={t('auth.guardian.name')} error={details.errors.guardian_name} required>
                    <Input
                        value={details.data.guardian_name}
                        onChange={(e) => details.setData('guardian_name', e.target.value)}
                    />
                </Field>
                <Field label={t('auth.guardian.phone')} error={details.errors.guardian_phone} required>
                    <PhoneInput value="" onChange={(_, raw) => details.setData('guardian_phone', raw)} />
                </Field>
                <Field label={t('auth.guardian.relationship')} error={details.errors.relationship}>
                    <Select
                        value={details.data.relationship}
                        onChange={(e) => details.setData('relationship', e.target.value)}
                    >
                        {relationships.map((relationship) => (
                            <option key={relationship} value={relationship}>
                                {t(`auth.guardian.${relationship}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Button type="submit" size="lg" block loading={details.processing}>
                    {t('auth.guardian.send')}
                </Button>
            </form>
        </AuthLayout>
    );
}
