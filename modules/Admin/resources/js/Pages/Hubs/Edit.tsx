import { Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { Switch } from '@/components/ui/Switch';
import { useTranslation } from '@/lib/i18n';

import { AdminPage } from '../../components/AdminPage';

interface HubForm {
    id: string;
    code: string;
    name: string;
    slug: string | null;
    description: string | null;
    municipalityId: number;
    placeName: string | null;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    phone: string | null;
    email: string | null;
    status: string;
    package: string;
    operatorId: string | null;
    openingHours: Record<string, string>;
}

interface Props {
    hub: HubForm | null;
    modules: { module: string; enabled: boolean; inPackage: boolean }[];
    staff: { userId: string; name: string | null; role: string }[];
    options: {
        cities: { id: number; name: string }[];
        operators: { id: string; name: string }[];
        packages: string[];
        statuses: string[];
    };
    canManage: boolean;
}

export default function HubEdit({ hub, modules, staff, options, canManage }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        code: hub?.code ?? '',
        name: hub?.name ?? '',
        slug: hub?.slug ?? '',
        description: hub?.description ?? '',
        municipality_id: hub?.municipalityId ? String(hub.municipalityId) : '',
        place_name: hub?.placeName ?? '',
        address: hub?.address ?? '',
        latitude: hub?.latitude ?? '',
        longitude: hub?.longitude ?? '',
        phone: hub?.phone ?? '',
        email: hub?.email ?? '',
        status: hub?.status ?? 'planned',
        package: hub?.package ?? 'base',
        operator_organisation_id: hub?.operatorId ?? '',
        opening_hours: { 'mon-fri': hub?.openingHours['mon-fri'] ?? '08:00-17:00', sat: hub?.openingHours.sat ?? '' },
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (hub) form.put(`/admin/hubs/${hub.id}`, { preserveScroll: true });
        else form.post('/admin/hubs');
    };

    const text = (key: keyof typeof form.data, label: string, hint?: string, required = false) => (
        <Field label={label} hint={hint} error={form.errors[key]} required={required}>
            <Input
                value={String(form.data[key] ?? '')}
                onChange={(e) => form.setData(key, e.target.value as never)}
                disabled={!canManage}
            />
        </Field>
    );

    const title = hub?.name ?? t('admin.hubs.new');

    return (
        <AdminPage title={title} crumbs={[{ label: t('admin.hubs.title'), href: '/admin/hubs' }, { label: title }]}>
            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardTitle>{t('admin.hubs.details')}</CardTitle>
                    <form onSubmit={submit} className="mt-4 grid gap-4 md:grid-cols-2" noValidate>
                        {text('name', t('admin.hubs.field.name'), undefined, true)}
                        {text('code', t('admin.hubs.field.code'), t('admin.hubs.field.code_hint'), true)}
                        {text('slug', t('admin.hubs.field.slug'), t('admin.hubs.field.slug_hint'), true)}
                        <Field label={t('admin.hubs.field.city')} error={form.errors.municipality_id} required>
                            <Select
                                value={form.data.municipality_id}
                                onChange={(e) => form.setData('municipality_id', e.target.value)}
                                disabled={!canManage}
                            >
                                <option value="">-</option>
                                {options.cities.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        {text('place_name', t('admin.hubs.field.place'))}
                        {text('address', t('admin.hubs.field.address'))}
                        {text('latitude', t('admin.hubs.field.latitude'))}
                        {text('longitude', t('admin.hubs.field.longitude'))}
                        {text('phone', t('admin.hubs.field.phone'))}
                        {text('email', t('admin.hubs.field.email'))}
                        <Field label={t('admin.hubs.field.hours_week')}>
                            <Input
                                value={form.data.opening_hours['mon-fri']}
                                onChange={(e) =>
                                    form.setData('opening_hours', {
                                        ...form.data.opening_hours,
                                        'mon-fri': e.target.value,
                                    })
                                }
                                disabled={!canManage}
                            />
                        </Field>
                        <Field label={t('admin.hubs.field.hours_sat')}>
                            <Input
                                value={form.data.opening_hours.sat}
                                onChange={(e) =>
                                    form.setData('opening_hours', { ...form.data.opening_hours, sat: e.target.value })
                                }
                                disabled={!canManage}
                            />
                        </Field>
                        <Field label={t('admin.hubs.field.status')} error={form.errors.status}>
                            <Select
                                value={form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)}
                                disabled={!canManage}
                            >
                                {options.statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('admin.hubs.package')} error={form.errors.package}>
                            <Select
                                value={form.data.package}
                                onChange={(e) => form.setData('package', e.target.value)}
                                disabled={!canManage}
                            >
                                {options.packages.map((p) => (
                                    <option key={p} value={p}>
                                        {p}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('admin.hubs.field.operator')} error={form.errors.operator_organisation_id}>
                            <Select
                                value={form.data.operator_organisation_id}
                                onChange={(e) => form.setData('operator_organisation_id', e.target.value)}
                                disabled={!canManage}
                            >
                                <option value="">-</option>
                                {options.operators.map((o) => (
                                    <option key={o.id} value={o.id}>
                                        {o.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field
                            label={t('admin.hubs.field.description')}
                            error={form.errors.description}
                            className="md:col-span-2"
                        >
                            <Textarea
                                rows={2}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                disabled={!canManage}
                            />
                        </Field>
                        {canManage && (
                            <div className="md:col-span-2">
                                <Button type="submit" loading={form.processing}>
                                    {t('account.save')}
                                </Button>
                            </div>
                        )}
                    </form>
                </Card>
                {hub && (
                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardTitle>{t('admin.hubs.services')}</CardTitle>
                            <ul className="mt-3">
                                {modules.map((m) => (
                                    <li key={m.module} className="border-line border-b last:border-0">
                                        <Switch
                                            label={m.module}
                                            checked={m.enabled}
                                            disabled={!canManage}
                                            onCheckedChange={(enabled) =>
                                                router.post(
                                                    `/admin/hubs/${hub.id}/modules`,
                                                    { module: m.module, enabled },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                        <p className="-mt-2 pb-2">
                                            <Badge tone={m.inPackage ? 'primary' : 'neutral'}>
                                                {m.inPackage ? t('admin.hubs.in_package') : t('admin.hubs.add_on')}
                                            </Badge>
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                        <Card>
                            <CardTitle>{t('admin.hubs.staff')}</CardTitle>
                            <ul className="mt-3 flex flex-col gap-2 text-sm">
                                {staff.length === 0 && <li className="text-fg-muted">{t('admin.none')}</li>}
                                {staff.map((s) => (
                                    <li key={`${s.userId}-${s.role}`} className="flex justify-between">
                                        <Link
                                            href={`/admin/people/${s.userId}`}
                                            className="text-primary hover:underline"
                                        >
                                            {s.name}
                                        </Link>
                                        <span className="text-fg-muted">{s.role}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </div>
                )}
            </div>
        </AdminPage>
    );
}
