import { router } from '@inertiajs/react';

import { DataTable } from '@/components/ui/DataTable';
import { Select } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

import { HubOpsPage } from '../components/HubOpsPage';

interface Row {
    id: string;
    name: string;
    visits: number;
    people: number;
    newMembers: number;
    events: number;
    attendanceRate: number | null;
}

export default function Compare({ period, rows }: { period: number; rows: Row[] }) {
    const { t } = useTranslation();

    return (
        <HubOpsPage
            title={t('hubops.compare.title')}
            crumbs={[{ label: t('hubops.compare.title') }]}
            actions={
                <Select
                    aria-label={t('hubops.dashboard.period')}
                    value={String(period)}
                    onChange={(e) => router.get('/hub-ops/compare', { period: e.target.value })}
                >
                    {[7, 30, 90].map((d) => (
                        <option key={d} value={d}>
                            {t('hubops.dashboard.days', { days: d })}
                        </option>
                    ))}
                </Select>
            }
        >
            <DataTable<Row>
                caption={t('hubops.compare.title')}
                rows={rows}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'name',
                        header: t('hubops.hub'),
                        cell: (r) => <span className="font-semibold">{r.name}</span>,
                    },
                    { key: 'visits', header: t('hubops.dashboard.visits'), cell: (r) => r.visits },
                    { key: 'people', header: t('hubops.dashboard.people'), cell: (r) => r.people },
                    {
                        key: 'new',
                        header: t('hubops.dashboard.new_members'),
                        cell: (r) => r.newMembers,
                        hideOnMobile: true,
                    },
                    { key: 'events', header: t('hubops.dashboard.events'), cell: (r) => r.events, hideOnMobile: true },
                    {
                        key: 'rate',
                        header: t('hubops.compare.rate'),
                        cell: (r) => (r.attendanceRate === null ? '-' : `${r.attendanceRate}%`),
                    },
                ]}
            />
        </HubOpsPage>
    );
}
