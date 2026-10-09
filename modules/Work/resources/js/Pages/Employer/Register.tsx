import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { FileInput } from '@/components/ui/FileInput';
import { Switch } from '@/components/ui/Switch';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    person: { name: string; assisted: boolean };
    cities: { id: number; name: string }[];
    options: { sectors: string[]; sizes: string[] };
    defaultCity: number | null;
}

export default function Register({ person, cities, options, defaultCity }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const form = useForm<{
        community: boolean;
        name: string;
        trading_name: string;
        registration_number: string;
        certificate: File | null;
        sector: string;
        size_band: string;
        municipality_id: string;
        address: string;
        contact_email: string;
        description: string;
        title: string;
        confirm: boolean;
    }>({
        community: false,
        name: '',
        trading_name: '',
        registration_number: '',
        certificate: null,
        sector: 'retail',
        size_band: '2-10',
        municipality_id: defaultCity ? String(defaultCity) : '',
        address: '',
        contact_email: '',
        description: '',
        title: '',
        confirm: false,
    });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.employer.register_title')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <div className="mx-auto max-w-2xl">
                <h1 className="text-fg mt-2 text-2xl font-bold">{t('work.employer.register_title')}</h1>
                <p className="text-fg-muted mt-1">{t('work.employer.register_lead')}</p>
                <Card className="mt-6">
                    <form
                        className="flex flex-col gap-4"
                        noValidate
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/work/employer/register', { forceFormData: true });
                        }}
                    >
                        <Switch
                            label={t('work.employer.community')}
                            checked={form.data.community}
                            onCheckedChange={(c) => form.setData('community', c)}
                        />
                        {form.data.community && (
                            <p className="text-fg-muted -mt-2 text-sm">{t('work.employer.community_hint')}</p>
                        )}
                        <Field
                            label={form.data.community ? t('work.employer.name_community') : t('work.employer.name')}
                            error={form.errors.name}
                            required
                        >
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label={t('work.employer.trading_name')} error={form.errors.trading_name}>
                            <Input
                                value={form.data.trading_name}
                                onChange={(e) => form.setData('trading_name', e.target.value)}
                            />
                        </Field>
                        {!form.data.community && (
                            <>
                                <Field
                                    label={t('work.employer.cipc')}
                                    hint={t('work.employer.cipc_hint')}
                                    error={form.errors.registration_number}
                                    required
                                >
                                    <Input
                                        value={form.data.registration_number}
                                        onChange={(e) => form.setData('registration_number', e.target.value)}
                                        inputMode="numeric"
                                    />
                                </Field>
                                <Field label={t('work.employer.certificate')} error={form.errors.certificate} required>
                                    <FileInput
                                        file={form.data.certificate}
                                        onChange={(file) => form.setData('certificate', file)}
                                        chooseLabel={t('documents.file')}
                                        photoLabel={t('documents.take_photo')}
                                    />
                                </Field>
                            </>
                        )}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('work.employer.sector')} error={form.errors.sector}>
                                <Select
                                    value={form.data.sector}
                                    onChange={(e) => form.setData('sector', e.target.value)}
                                >
                                    {options.sectors.map((s) => (
                                        <option key={s} value={s}>
                                            {t(`work.sector.${s}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('work.employer.size')} error={form.errors.size_band}>
                                <Select
                                    value={form.data.size_band}
                                    onChange={(e) => form.setData('size_band', e.target.value)}
                                >
                                    {options.sizes.map((s) => (
                                        <option key={s} value={s}>
                                            {s}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                        <Field label={t('work.employer.city')} error={form.errors.municipality_id} required>
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
                        <Field label={t('work.employer.address')} error={form.errors.address}>
                            <Input
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                            />
                        </Field>
                        <Field label={t('work.employer.email')} error={form.errors.contact_email}>
                            <Input
                                type="email"
                                value={form.data.contact_email}
                                onChange={(e) => form.setData('contact_email', e.target.value)}
                            />
                        </Field>
                        <Field label={t('work.employer.description')} error={form.errors.description}>
                            <Textarea
                                rows={3}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <Field label={t('work.employer.your_title')} error={form.errors.title}>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>
                        <Checkbox
                            label={t('work.employer.confirm')}
                            checked={form.data.confirm}
                            onCheckedChange={(c) => form.setData('confirm', c === true)}
                        />
                        {form.errors.confirm && <p className="text-danger-text text-sm">{form.errors.confirm}</p>}
                        <div>
                            <Button type="submit" loading={form.processing} disabled={!form.data.confirm}>
                                {t('work.employer.submit')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
