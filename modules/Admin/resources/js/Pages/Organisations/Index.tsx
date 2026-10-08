import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/display';
import { Select } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

import { AdminPage, PageLinks, type Paginated } from '../../components/AdminPage';

interface OrgRow {
    id: string;
    name: string;
    type: string;
    status: string;
    members: number;
    registration: string | null;
}

const TONE: Record<string, 'warning' | 'success' | 'danger' | 'neutral'> = {
    pending: 'warning',
    verified: 'success',
    rejected: 'danger',
    suspended: 'neutral',
};

export default function OrganisationsIndex({
    filters,
    organisations,
    types,
    canManage,
}: {
    filters: { status: string | null; type: string | null };
    organisations: Paginated<OrgRow>;
    types: string[];
    canManage: boolean;
}) {
    const { t } = useTranslation();
    const filter = (key: string, value: string) =>
        router.get('/admin/organisations', { ...filters, [key]: value || undefined }, { preserveState: true });

    return (
        <AdminPage
            title={t('admin.organisations.title')}
            crumbs={[{ label: t('admin.organisations.title') }]}
            actions={
                canManage && (
                    <Link href="/admin/organisations/create" className={buttonVariants({})}>
                        <Plus className="size-4" aria-hidden /> {t('admin.organisations.new')}
                    </Link>
                )
            }
        >
            <div className="mb-4 flex flex-wrap gap-3">
                <Select
                    aria-label={t('admin.hubs.field.status')}
                    className="max-w-48"
                    value={filters.status ?? ''}
                    onChange={(e) => filter('status', e.target.value)}
                >
                    <option value="">{t('admin.all')}</option>
                    {['pending', 'verified', 'rejected', 'suspended'].map((s) => (
                        <option key={s} value={s}>
                            {t(`admin.organisations.status.${s}`)}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label={t('admin.organisations.field.type')}
                    className="max-w-56"
                    value={filters.type ?? ''}
                    onChange={(e) => filter('type', e.target.value)}
                >
                    <option value="">{t('admin.all')}</option>
                    {types.map((type) => (
                        <option key={type} value={type}>
                            {t(`admin.organisations.type.${type}`)}
                        </option>
                    ))}
                </Select>
            </div>
            <DataTable<OrgRow>
                caption={t('admin.organisations.title')}
                rows={organisations.data}
                rowKey={(o) => o.id}
                empty={<p className="text-fg-muted">{t('admin.none')}</p>}
                columns={[
                    {
                        key: 'name',
                        header: t('admin.organisations.field.name'),
                        cell: (o) => (
                            <Link
                                href={`/admin/organisations/${o.id}`}
                                className="text-primary font-semibold hover:underline"
                            >
                                {o.name}
                            </Link>
                        ),
                    },
                    {
                        key: 'type',
                        header: t('admin.organisations.field.type'),
                        cell: (o) => t(`admin.organisations.type.${o.type}`),
                    },
                    {
                        key: 'status',
                        header: t('admin.hubs.field.status'),
                        cell: (o) => (
                            <Badge tone={TONE[o.status] ?? 'neutral'}>
                                {t(`admin.organisations.status.${o.status}`)}
                            </Badge>
                        ),
                    },
                    {
                        key: 'members',
                        header: t('admin.organisations.members'),
                        cell: (o) => o.members,
                        hideOnMobile: true,
                    },
                ]}
            />
            <PageLinks page={organisations} />
        </AdminPage>
    );
}
