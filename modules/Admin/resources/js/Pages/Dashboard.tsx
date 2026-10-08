import { StatCard } from '@/components/ui/display';
import { Card, CardTitle } from '@/components/ui/display';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage } from '../components/AdminPage';
import { BarChart } from '../components/BarChart';

interface DashboardProps {
    kpis: Record<string, number>;
    registrations: { week: string; count: number }[];
    hubsByStatus: Record<string, number>;
}

const number = (value: number) => new Intl.NumberFormat('en-ZA').format(value);

export default function Dashboard({ kpis, registrations, hubsByStatus }: DashboardProps) {
    const { t } = useTranslation();
    const attention = ['documentsWaiting', 'organisationsWaiting', 'enquiriesOpen', 'failedMessages7d'];

    return (
        <AdminPage title={t('admin.dashboard.title')}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {['people', 'newThisWeek', 'hubsLive'].map((key) => (
                    <StatCard
                        key={key}
                        label={t(`admin.dashboard.kpi.${key}`)}
                        value={number(kpis[key] ?? 0)}
                        emphasis={key === 'people'}
                    />
                ))}
            </div>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {attention.map((key) => (
                    <StatCard key={key} label={t(`admin.dashboard.kpi.${key}`)} value={number(kpis[key] ?? 0)} />
                ))}
            </div>
            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardTitle>{t('admin.dashboard.registrations')}</CardTitle>
                    <div className="mt-4">
                        <BarChart
                            label={t('admin.dashboard.registrations')}
                            data={registrations.map((r) => ({
                                label: formatDate(r.week).replace(/ \d{4}$/, ''),
                                value: r.count,
                            }))}
                        />
                    </div>
                </Card>
                <Card>
                    <CardTitle>{t('admin.dashboard.hubs_by_status')}</CardTitle>
                    <dl className="mt-4 flex flex-col gap-2">
                        {Object.entries(hubsByStatus).map(([status, count]) => (
                            <div key={status} className="flex justify-between">
                                <dt className="text-fg-muted capitalize">{status}</dt>
                                <dd className="text-fg font-semibold">{count}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>
            </div>
        </AdminPage>
    );
}
