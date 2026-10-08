import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/display';
import { Field, SearchInput, Select } from '@/components/ui/form';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage, PageLinks, type Paginated } from '../../components/AdminPage';

interface Person {
    id: string;
    name: string;
    phone: string;
    hub: string | null;
    status: string;
    joined: string | null;
}

interface Props {
    filters: Record<string, string | undefined>;
    people: Paginated<Person>;
    options: {
        hubs: { id: string; name: string }[];
        provinces: { id: number; name: string }[];
        roles: { key: string; label: string }[];
        statuses: string[];
    };
}

const STATUS_TONE: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    active: 'success',
    pending_guardian: 'warning',
    suspended: 'danger',
    deletion_requested: 'neutral',
};

export default function PeopleIndex({ filters, people, options }: Props) {
    const { t } = useTranslation();
    const [values, setValues] = useState<Record<string, string>>({
        q: filters.q ?? '',
        hub: filters.hub ?? '',
        province: filters.province ?? '',
        role: filters.role ?? '',
        status: filters.status ?? '',
    });
    const set = (key: string, value: string) => setValues((v) => ({ ...v, [key]: value }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/people', Object.fromEntries(Object.entries(values).filter(([, v]) => v !== '')), {
            preserveState: true,
        });
    };

    return (
        <AdminPage title={t('admin.people.title')} crumbs={[{ label: t('admin.people.title') }]}>
            <form
                onSubmit={submit}
                className="rounded-card border-line bg-surface grid gap-4 border p-4 md:grid-cols-6"
            >
                <Field label={t('admin.search')} className="md:col-span-2">
                    <SearchInput
                        value={values.q}
                        onChange={(e) => set('q', e.target.value)}
                        placeholder={t('admin.people.search')}
                        aria-label={t('admin.search')}
                    />
                </Field>
                <Field label={t('admin.people.col.hub')}>
                    <Select value={values.hub} onChange={(e) => set('hub', e.target.value)}>
                        <option value="">{t('admin.all')}</option>
                        {options.hubs.map((h) => (
                            <option key={h.id} value={h.id}>
                                {h.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('profile.province')}>
                    <Select value={values.province} onChange={(e) => set('province', e.target.value)}>
                        <option value="">{t('admin.all')}</option>
                        {options.provinces.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('admin.people.role')}>
                    <Select value={values.role} onChange={(e) => set('role', e.target.value)}>
                        <option value="">{t('admin.all')}</option>
                        {options.roles.map((r) => (
                            <option key={r.key} value={r.key}>
                                {r.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('admin.people.col.status')}>
                    <Select value={values.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">{t('admin.all')}</option>
                        {options.statuses.map((s) => (
                            <option key={s} value={s}>
                                {t(`admin.status.${s}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="md:col-span-6">
                    <Button type="submit">{t('admin.filter')}</Button>
                </div>
            </form>
            <div className="mt-6">
                <DataTable<Person>
                    caption={t('admin.people.title')}
                    rows={people.data}
                    rowKey={(p) => p.id}
                    empty={<p className="text-fg-muted">{t('admin.none')}</p>}
                    columns={[
                        {
                            key: 'name',
                            header: t('admin.people.col.name'),
                            cell: (p) => (
                                <Link
                                    href={`/admin/people/${p.id}`}
                                    className="text-primary font-semibold hover:underline"
                                >
                                    {p.name}
                                </Link>
                            ),
                        },
                        { key: 'phone', header: t('admin.people.col.phone'), cell: (p) => p.phone },
                        {
                            key: 'hub',
                            header: t('admin.people.col.hub'),
                            cell: (p) => p.hub ?? '-',
                            hideOnMobile: true,
                        },
                        {
                            key: 'status',
                            header: t('admin.people.col.status'),
                            cell: (p) => (
                                <Badge tone={STATUS_TONE[p.status] ?? 'neutral'}>{t(`admin.status.${p.status}`)}</Badge>
                            ),
                        },
                        {
                            key: 'joined',
                            header: t('admin.people.col.joined'),
                            cell: (p) => (p.joined ? formatDate(p.joined) : '-'),
                            hideOnMobile: true,
                        },
                    ]}
                />
                <PageLinks page={people} />
            </div>
        </AdminPage>
    );
}
