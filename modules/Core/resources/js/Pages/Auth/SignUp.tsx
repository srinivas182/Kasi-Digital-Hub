import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/form';
import { Checkbox } from '@/components/ui/Checkbox';
import { Switch } from '@/components/ui/Switch';
import { Stepper } from '@/components/ui/Stepper';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

import { PinFields } from '../../components/PinFields';

interface LegalDocument {
    key: string;
    version: number;
    title: string;
    summary: string;
}

interface SignUpProps {
    phone: string;
    purposes: string[];
    documents: LegalDocument[];
}

const STEP_FIELDS: Record<string, number> = {
    pin: 0,
    date_of_birth: 1,
    first_name: 2,
    last_name: 2,
    preferred_name: 2,
    accept_terms: 3,
};

export default function SignUp({ phone, purposes, documents }: SignUpProps) {
    const { t } = useTranslation();
    const [step, setStep] = useState(0);
    const form = useForm({
        pin: '',
        pin_confirmation: '',
        date_of_birth: '',
        first_name: '',
        last_name: '',
        preferred_name: '',
        accept_terms: false,
        consents: Object.fromEntries(purposes.map((purpose) => [purpose, false])) as Record<string, boolean>,
        remember: false,
    });

    const steps = [
        t('auth.signup.step_pin'),
        t('auth.signup.step_birth'),
        t('auth.signup.step_name'),
        t('auth.signup.step_consent'),
    ];
    const canContinue = [
        form.data.pin.length === 5 && form.data.pin === form.data.pin_confirmation,
        form.data.date_of_birth !== '',
        form.data.first_name.trim() !== '' && form.data.last_name.trim() !== '',
        form.data.accept_terms,
    ][step];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (step < 3) {
            setStep(step + 1);
            return;
        }
        form.post('/signup', {
            // Jump back to the step that has a server-side error.
            onError: (errors) => {
                const first = Object.keys(errors)[0];
                if (first && STEP_FIELDS[first] !== undefined) setStep(STEP_FIELDS[first]);
            },
        });
    };

    return (
        <AuthLayout title={t('auth.signup.title')} description={phone}>
            <Head title={t('auth.signup.title')} />
            <Stepper steps={steps} current={step} />
            <form onSubmit={submit} className="mt-6 flex flex-col gap-6" noValidate>
                {step === 0 && (
                    <PinFields
                        pin={form.data.pin}
                        confirmation={form.data.pin_confirmation}
                        onPin={(value) => form.setData('pin', value)}
                        onConfirmation={(value) => form.setData('pin_confirmation', value)}
                        error={form.errors.pin}
                    />
                )}

                {step === 1 && (
                    <Field
                        label={t('auth.signup.date_of_birth')}
                        hint={t('auth.signup.date_of_birth_hint')}
                        error={form.errors.date_of_birth}
                        required
                    >
                        <Input
                            type="date"
                            value={form.data.date_of_birth}
                            onChange={(e) => form.setData('date_of_birth', e.target.value)}
                            autoComplete="bday"
                        />
                    </Field>
                )}

                {step === 2 && (
                    <>
                        <Field label={t('auth.signup.first_name')} error={form.errors.first_name} required>
                            <Input
                                value={form.data.first_name}
                                onChange={(e) => form.setData('first_name', e.target.value)}
                                autoComplete="given-name"
                            />
                        </Field>
                        <Field label={t('auth.signup.last_name')} error={form.errors.last_name} required>
                            <Input
                                value={form.data.last_name}
                                onChange={(e) => form.setData('last_name', e.target.value)}
                                autoComplete="family-name"
                            />
                        </Field>
                        <Field label={t('auth.signup.preferred_name')} error={form.errors.preferred_name}>
                            <Input
                                value={form.data.preferred_name}
                                onChange={(e) => form.setData('preferred_name', e.target.value)}
                                autoComplete="nickname"
                            />
                        </Field>
                    </>
                )}

                {step === 3 && (
                    <>
                        <div className="border-line rounded-card flex flex-col gap-3 border p-4">
                            {documents.map((document) => (
                                <div key={document.key}>
                                    <p className="text-fg text-sm font-semibold">{document.title}</p>
                                    <p className="text-fg-muted text-sm">{document.summary}</p>
                                    <Link
                                        href={`/legal/${document.key}`}
                                        target="_blank"
                                        className="text-primary text-sm font-semibold hover:underline"
                                    >
                                        {t('auth.signup.read')} {document.title}
                                    </Link>
                                </div>
                            ))}
                            <Checkbox
                                label={t('auth.signup.accept_terms')}
                                checked={form.data.accept_terms}
                                onCheckedChange={(checked) => form.setData('accept_terms', checked === true)}
                            />
                            {form.errors.accept_terms && (
                                <p role="alert" className="text-danger-text text-sm font-medium">
                                    {form.errors.accept_terms}
                                </p>
                            )}
                        </div>
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('auth.signup.optional_title')}</legend>
                            <p className="text-fg-muted mb-2 text-sm">{t('auth.signup.optional_hint')}</p>
                            {purposes.map((purpose) => (
                                <div key={purpose} className="border-line border-b py-1 last:border-0">
                                    <Switch
                                        label={t(`account.purpose.${purpose}`)}
                                        checked={form.data.consents[purpose] ?? false}
                                        onCheckedChange={(checked) =>
                                            form.setData('consents', { ...form.data.consents, [purpose]: checked })
                                        }
                                    />
                                    <p className="text-fg-muted pb-2 text-xs">{t(`account.purpose.${purpose}_hint`)}</p>
                                </div>
                            ))}
                        </fieldset>
                        <Checkbox
                            label={t('auth.pin.remember')}
                            checked={form.data.remember}
                            onCheckedChange={(c) => form.setData('remember', c === true)}
                        />
                    </>
                )}

                <div className="flex gap-3">
                    {step > 0 && (
                        <Button variant="secondary" size="lg" onClick={() => setStep(step - 1)}>
                            {t('auth.signup.back')}
                        </Button>
                    )}
                    <Button
                        type="submit"
                        size="lg"
                        className="flex-1"
                        disabled={!canContinue}
                        loading={form.processing}
                    >
                        {step < 3 ? t('auth.signup.next') : t('auth.signup.create')}
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
