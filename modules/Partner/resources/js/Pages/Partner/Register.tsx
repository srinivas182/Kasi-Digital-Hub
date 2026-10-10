import { Head, useForm, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

export default function PartnerRegister({
    cities,
    defaultCity,
}: {
    cities: { id: number; name: string }[];
    defaultCity: number | null;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const form = useForm<{
        name: string;
        trading_name: string;
        registration_number: string;
        certificate: File | null;
        municipality_id: string;
        contact_email: string;
        description: string;
        confirm: boolean;
    }>({
        name: '',
        trading_name: '',
        registration_number: '',
        certificate: null,
        municipality_id: defaultCity ? String(defaultCity) : '',
        contact_email: '',
        description: '',
        confirm: false,
    });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('partner.register.title')} />
            <div className="mx-auto max-w-2xl">
                <h1 className="text-fg text-2xl font-bold">{t('partner.register.title')}</h1>
                <p className="text-fg-muted mt-1">{t('partner.register.lead')}</p>
                <Card className="mt-6">
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/partner/register', { forceFormData: true });
                        }}
                    >
                        <Field label={t('work.employer.name')} error={form.errors.name} required>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label={t('work.employer.trading_name')} error={form.errors.trading_name}>
                            <Input
                                value={form.data.trading_name}
                                onChange={(e) => form.setData('trading_name', e.target.value)}
                            />
                        </Field>
                        <Field label={t('work.employer.cipc')} error={form.errors.registration_number} required>
                            <Input
                                value={form.data.registration_number}
                                onChange={(e) => form.setData('registration_number', e.target.value)}
                            />
                        </Field>
                        <Field label={t('work.employer.certificate')} error={form.errors.certificate} required>
                            <FileInput
                                file={form.data.certificate}
                                onChange={(f) => form.setData('certificate', f)}
                                chooseLabel={t('documents.file')}
                                photoLabel={t('documents.take_photo')}
                            />
                        </Field>
                        <Field label={t('start.form.city')} error={form.errors.municipality_id} required>
                            <Select
                                value={form.data.municipality_id}
                                onChange={(e) => form.setData('municipality_id', e.target.value)}
                            >
                                <option value="">-</option>
                                {cities.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('work.employer.email')} error={form.errors.contact_email}>
                            <Input
                                type="email"
                                value={form.data.contact_email}
                                onChange={(e) => form.setData('contact_email', e.target.value)}
                            />
                        </Field>
                        <Field label={t('partner.register.description')} error={form.errors.description}>
                            <Textarea
                                rows={3}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <Checkbox
                            label={t('partner.register.confirm')}
                            checked={form.data.confirm}
                            onCheckedChange={(c) => form.setData('confirm', c === true)}
                        />
                        <div>
                            <Button type="submit" loading={form.processing} disabled={!form.data.confirm}>
                                {t('partner.register.submit')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
