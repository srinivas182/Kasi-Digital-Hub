import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/display';
import { useTranslation } from '@/lib/i18n';

import { AdminPage } from '../../components/AdminPage';

interface HubRow {
    id: string;
    code: string;
    name: string;
    city: string;
    province: string;
    status: string;
    package: string;
    people: number;
}

export default function HubsIndex({ hubs, canManage }: { hubs: HubRow[]; canManage: boolean }) {
    const { t } = useTranslation();
    return (
        <AdminPage
            title={t('admin.hubs.title')}
            crumbs={[{ label: t('admin.hubs.title') }]}
            actions={
                canManage && (
                    <Link href="/admin/hubs/create" className={buttonVariants({})}>
                        <Plus className="size-4" aria-hidden /> {t('admin.hubs.new')}
                    </Link>
                )
            }
        >
            <DataTable<HubRow>
                caption={t('admin.hubs.title')}
                rows={hubs}
                rowKey={(h) => h.id}
                columns={[
                    {
                        key: 'name',
                        header: t('admin.hubs.field.name'),
                        cell: (h) => (
                            <Link href={`/admin/hubs/${h.id}`} className="text-primary font-semibold hover:underline">
                                {h.name}
                            </Link>
                        ),
                    },
                    {
                        key: 'code',
                        header: t('admin.hubs.field.code'),
                        cell: (h) => <span className="font-mono text-xs">{h.code}</span>,
                        hideOnMobile: true,
                    },
                    {
                        key: 'city',
                        header: t('admin.hubs.field.city'),
                        cell: (h) => `${h.city}, ${h.province}`,
                        hideOnMobile: true,
                    },
                    {
                        key: 'status',
                        header: t('admin.hubs.field.status'),
                        cell: (h) => (
                            <Badge
                                tone={h.status === 'live' ? 'success' : h.status === 'planned' ? 'warning' : 'neutral'}
                            >
                                {h.status}
                            </Badge>
                        ),
                    },
                    {
                        key: 'package',
                        header: t('admin.hubs.package'),
                        cell: (h) => <span className="capitalize">{h.package}</span>,
                    },
                    { key: 'people', header: t('admin.hubs.col.people'), cell: (h) => h.people, hideOnMobile: true },
                ]}
            />
        </AdminPage>
    );
}
