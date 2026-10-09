import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { useTranslation } from '@/lib/i18n';

import { AdminPage } from '../../components/AdminPage';
import { ReasonDialog } from '../../components/ReasonDialog';

interface Props {
    organisation: {
        id: string;
        name: string;
        type: string;
        registrationNumber: string | null;
        status: string;
        email: string | null;
        phone: string | null;
        municipalityId: number | null;
        address: string | null;
        community?: boolean;
        documentUrl?: string | null;
        checklist?: { item: string; checked: boolean }[];
    } | null;
    members: { id: string; name: string; phone: string; title: string | null }[];
    types: string[];
    cities: { id: number; name: string }[];
    can: { manage: boolean; verify: boolean };
}

export default function OrganisationShow({ organisation, members, types, cities, can }: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const form = useForm({
        name: organisation?.name ?? '',
        type: organisation?.type ?? 'employer',
        registration_number: organisation?.registrationNumber ?? '',
        contact_email: organisation?.email ?? '',
        contact_phone: organisation?.phone ?? '',
        municipality_id: organisation?.municipalityId ? String(organisation.municipalityId) : '',
        address: organisation?.address ?? '',
    });
    const member = useForm({ phone: '', title: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (organisation) form.put(`/admin/organisations/${organisation.id}`, { preserveScroll: true });
        else form.post('/admin/organisations');
    };

    const title = organisation?.name ?? t('admin.organisations.new');

    return (
        <AdminPage
            title={title}
            crumbs={[{ label: t('admin.organisations.title'), href: '/admin/organisations' }, { label: title }]}
            actions={
                organisation &&
                can.verify &&
                organisation.status !== 'verified' && (
                    <>
                        <Button
                            onClick={() =>
                                router.post(
                                    `/admin/organisations/${organisation.id}/verify`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('admin.organisations.verify')}
                        </Button>
                        <ReasonDialog
                            trigger={<Button variant="danger">{t('admin.organisations.reject')}</Button>}
                            title={t('admin.organisations.reject')}
                            confirmLabel={t('admin.organisations.reject')}
                            action={`/admin/organisations/${organisation.id}/reject`}
                            danger
                        />
                    </>
                )
            }
        >
            {organisation && (
                <div className="mb-4">
                    <Badge
                        tone={
                            organisation.status === 'verified'
                                ? 'success'
                                : organisation.status === 'pending'
                                  ? 'warning'
                                  : 'danger'
                        }
                    >
                        {t(`admin.organisations.status.${organisation.status}`)}
                    </Badge>
                </div>
            )}
            {organisation && (organisation.checklist?.length ?? 0) > 0 && (
                <Card className="mb-6">
                    <CardTitle>{t('admin.organisations.checklist')}</CardTitle>
                    {organisation.community && (
                        <p className="text-fg mt-1 text-sm font-semibold">{t('admin.organisations.community')}</p>
                    )}
                    {organisation.documentUrl && (
                        <a
                            href={organisation.documentUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary mt-2 inline-block text-sm font-semibold hover:underline"
                        >
                            {t('admin.organisations.certificate')}
                        </a>
                    )}
                    {errors?.checklist && <p className="text-danger-text mt-2 text-sm">{errors.checklist}</p>}
                    <ul className="mt-3 flex flex-col gap-1">
                        {organisation.checklist?.map((c) => (
                            <li key={c.item}>
                                <Checkbox
                                    label={t(`admin.check.${c.item}`)}
                                    checked={c.checked}
                                    disabled={!can.verify}
                                    onCheckedChange={(checked) =>
                                        router.post(
                                            `/admin/organisations/${organisation.id}/checklist`,
                                            { item: c.item, checked: checked === true },
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2" noValidate>
                        <Field label={t('admin.organisations.field.name')} error={form.errors.name} required>
                            <Input
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                disabled={!can.manage}
                            />
                        </Field>
                        <Field label={t('admin.organisations.field.type')} error={form.errors.type} required>
                            <Select
                                value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}
                                disabled={!can.manage}
                            >
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {t(`admin.organisations.type.${type}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field
                            label={t('admin.organisations.field.registration')}
                            error={form.errors.registration_number}
                        >
                            <Input
                                value={form.data.registration_number}
                                onChange={(e) => form.setData('registration_number', e.target.value)}
                                disabled={!can.manage}
                            />
                        </Field>
                        <Field label={t('admin.organisations.field.city')} error={form.errors.municipality_id}>
                            <Select
                                value={form.data.municipality_id}
                                onChange={(e) => form.setData('municipality_id', e.target.value)}
                                disabled={!can.manage}
                            >
                                <option value="">-</option>
                                {cities.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('admin.organisations.field.email')} error={form.errors.contact_email}>
                            <Input
                                type="email"
                                value={form.data.contact_email}
                                onChange={(e) => form.setData('contact_email', e.target.value)}
                                disabled={!can.manage}
                            />
                        </Field>
                        <Field label={t('admin.organisations.field.phone')} error={form.errors.contact_phone}>
                            <Input
                                value={form.data.contact_phone}
                                onChange={(e) => form.setData('contact_phone', e.target.value)}
                                disabled={!can.manage}
                            />
                        </Field>
                        <Field
                            label={t('admin.organisations.field.address')}
                            error={form.errors.address}
                            className="md:col-span-2"
                        >
                            <Input
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                                disabled={!can.manage}
                            />
                        </Field>
                        {can.manage && (
                            <div className="md:col-span-2">
                                <Button type="submit" loading={form.processing}>
                                    {t('account.save')}
                                </Button>
                            </div>
                        )}
                    </form>
                </Card>
                {organisation && (
                    <Card>
                        <CardTitle>{t('admin.organisations.members')}</CardTitle>
                        <ul className="mt-3 flex flex-col gap-2 text-sm">
                            {members.length === 0 && <li className="text-fg-muted">{t('admin.none')}</li>}
                            {members.map((m) => (
                                <li key={m.id} className="flex items-center justify-between gap-2">
                                    <span>
                                        <span className="text-fg block font-semibold">{m.name}</span>
                                        <span className="text-fg-muted block text-xs">
                                            {m.phone}
                                            {m.title ? ` · ${m.title}` : ''}
                                        </span>
                                    </span>
                                    {can.manage && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(
                                                    `/admin/organisations/${organisation.id}/members/${m.id}`,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('admin.people.remove')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                        {can.manage && (
                            <form
                                className="border-line mt-4 flex flex-col gap-3 border-t pt-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    member.post(`/admin/organisations/${organisation.id}/members`, {
                                        preserveScroll: true,
                                        onSuccess: () => member.reset(),
                                    });
                                }}
                            >
                                <Field label={t('admin.organisations.add_member')} error={member.errors.phone}>
                                    <PhoneInput value="" onChange={(_, raw) => member.setData('phone', raw)} />
                                </Field>
                                <Field label={t('admin.organisations.field.title')}>
                                    <Input
                                        value={member.data.title}
                                        onChange={(e) => member.setData('title', e.target.value)}
                                    />
                                </Field>
                                <Button type="submit" variant="secondary" loading={member.processing}>
                                    {t('admin.organisations.add_member')}
                                </Button>
                            </form>
                        )}
                    </Card>
                )}
            </div>
        </AdminPage>
    );
}
