import { useForm } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { OtpInput } from '@/components/ui/OtpInput';
import { Switch } from '@/components/ui/Switch';
import { useTranslation } from '@/lib/i18n';
import { PinFields } from '@modules/Core/resources/js/components/PinFields';

import { HubOpsPage } from '../../components/HubOpsPage';

interface Props {
    phone: string;
    demoCode: string | null;
    purposes: string[];
    visitPurposes: string[];
}

/** Step 2: details with the person, then the person types their own PIN. */
export default function RegisterDetails({ phone, demoCode, purposes, visitPurposes }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        code: '',
        first_name: '',
        last_name: '',
        preferred_name: '',
        date_of_birth: '',
        pin: '',
        pin_confirmation: '',
        consents: Object.fromEntries(purposes.map((p) => [p, false])) as Record<string, boolean>,
        visit_purpose: 'jobs',
        accept_terms: false,
        present: false,
    });

    return (
        <HubOpsPage
            title={t('hubops.register.title')}
            crumbs={[
                { label: t('hubops.checkin.title'), href: '/hub-ops/check-in' },
                { label: t('hubops.register.title') },
            ]}
        >
            <form
                className="grid max-w-4xl gap-6 lg:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hub-ops/register');
                }}
                noValidate
            >
                <Card className="flex flex-col gap-4">
                    <CardTitle>{t('hubops.register.details_title')}</CardTitle>
                    <p className="text-fg-muted text-sm">{phone}</p>
                    {demoCode && <Alert tone="info" title={t('auth.code.demo', { code: demoCode })} />}
                    <div>
                        <p className="text-fg mb-2 text-sm font-semibold">{t('hubops.register.code_label')}</p>
                        <OtpInput
                            value={form.data.code}
                            onChange={(v) => form.setData('code', v)}
                            label={t('hubops.register.code_label')}
                            invalid={Boolean(form.errors.code)}
                        />
                        {form.errors.code && <p className="text-danger-text mt-1 text-sm">{form.errors.code}</p>}
                    </div>
                    <Field label={t('auth.signup.first_name')} error={form.errors.first_name} required>
                        <Input
                            value={form.data.first_name}
                            onChange={(e) => form.setData('first_name', e.target.value)}
                            autoComplete="off"
                        />
                    </Field>
                    <Field label={t('auth.signup.last_name')} error={form.errors.last_name} required>
                        <Input
                            value={form.data.last_name}
                            onChange={(e) => form.setData('last_name', e.target.value)}
                            autoComplete="off"
                        />
                    </Field>
                    <Field label={t('auth.signup.preferred_name')} error={form.errors.preferred_name}>
                        <Input
                            value={form.data.preferred_name}
                            onChange={(e) => form.setData('preferred_name', e.target.value)}
                            autoComplete="off"
                        />
                    </Field>
                    <Field label={t('auth.signup.date_of_birth')} error={form.errors.date_of_birth} required>
                        <Input
                            type="date"
                            value={form.data.date_of_birth}
                            onChange={(e) => form.setData('date_of_birth', e.target.value)}
                        />
                    </Field>
                    <Field label={t('hubops.checkin.purpose')} error={form.errors.visit_purpose}>
                        <Select
                            value={form.data.visit_purpose}
                            onChange={(e) => form.setData('visit_purpose', e.target.value)}
                        >
                            {visitPurposes.map((p) => (
                                <option key={p} value={p}>
                                    {t(`hubops.purpose.${p}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <fieldset>
                        <legend className="text-fg text-sm font-semibold">{t('hubops.register.consents')}</legend>
                        {purposes.map((p) => (
                            <div key={p} className="border-line border-b py-1 last:border-0">
                                <Switch
                                    label={t(`account.purpose.${p}`)}
                                    checked={form.data.consents[p] ?? false}
                                    onCheckedChange={(c) => form.setData('consents', { ...form.data.consents, [p]: c })}
                                />
                            </div>
                        ))}
                    </fieldset>
                </Card>

                <Card className="flex flex-col gap-4">
                    <CardTitle>{t('hubops.register.pin_title')}</CardTitle>
                    <p className="text-fg-muted text-sm">{t('hubops.register.pin_intro')}</p>
                    <PinFields
                        pin={form.data.pin}
                        confirmation={form.data.pin_confirmation}
                        onPin={(v) => form.setData('pin', v)}
                        onConfirmation={(v) => form.setData('pin_confirmation', v)}
                        error={form.errors.pin}
                    />
                    <Checkbox
                        label={t('hubops.register.terms')}
                        checked={form.data.accept_terms}
                        onCheckedChange={(c) => form.setData('accept_terms', c === true)}
                    />
                    {form.errors.accept_terms && <p className="text-danger-text text-sm">{form.errors.accept_terms}</p>}
                    <Checkbox
                        label={t('hubops.register.present')}
                        checked={form.data.present}
                        onCheckedChange={(c) => form.setData('present', c === true)}
                    />
                    {form.errors.present && <p className="text-danger-text text-sm">{form.errors.present}</p>}
                    <Button
                        type="submit"
                        loading={form.processing}
                        disabled={
                            !form.data.present ||
                            !form.data.accept_terms ||
                            form.data.pin.length !== 5 ||
                            form.data.pin !== form.data.pin_confirmation
                        }
                    >
                        {t('hubops.register.submit')}
                    </Button>
                </Card>
            </form>
        </HubOpsPage>
    );
}
