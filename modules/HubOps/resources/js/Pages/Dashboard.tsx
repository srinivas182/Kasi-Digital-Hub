import { Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { Card, CardTitle, ProgressBar, StatCard } from '@/components/ui/display';
import { Select } from '@/components/ui/form';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { BarChart } from '../components/BarChart';
import { type HubChoice, HubOpsPage } from '../components/HubOpsPage';

interface Props {
    hubs: HubChoice;
    period: number;
    canCompare: boolean;
    metrics: {
        totals: Record<
            'visits' | 'people' | 'walkIns' | 'newMembers' | 'documentsVerified' | 'events' | 'signedUp' | 'attended',
            number
        >;
        byDay: { date: string; visits: number }[];
        purposes: Record<string, number>;
    };
}

const number = (value: number) => new Intl.NumberFormat('en-ZA').format(value);

export default function Dashboard({ hubs, period, metrics, canCompare }: Props) {
    const { t } = useTranslation();
    const { totals } = metrics;
    const purposeTotal = Object.values(metrics.purposes).reduce((a, b) => a + b, 0) || 1;
    const days = period > 30 ? metrics.byDay.filter((_, i) => i % 3 === 0) : metrics.byDay;

    return (
        <HubOpsPage
            title={t('hubops.dashboard.title')}
            hubs={hubs}
            actions={
                <>
                    <Select
                        aria-label={t('hubops.dashboard.period')}
                        value={String(period)}
                        onChange={(e) =>
                            router.get(
                                '/hub-ops',
                                { hub: hubs.current.id, period: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {[7, 30, 90].map((d) => (
                            <option key={d} value={d}>
                                {t('hubops.dashboard.days', { days: d })}
                            </option>
                        ))}
                    </Select>
                    <a
                        href={`/hub-ops/export?hub=${hubs.current.id}&period=${period}`}
                        className={buttonVariants({ variant: 'secondary' })}
                    >
                        <Download className="size-4" aria-hidden /> {t('hubops.dashboard.export')}
                    </a>
                    {canCompare && (
                        <Link
                            href={`/hub-ops/compare?period=${period}`}
                            className={buttonVariants({ variant: 'ghost' })}
                        >
                            {t('hubops.dashboard.compare')}
                        </Link>
                    )}
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label={t('hubops.dashboard.visits')} value={number(totals.visits)} emphasis />
                <StatCard label={t('hubops.dashboard.people')} value={number(totals.people)} />
                <StatCard label={t('hubops.dashboard.new_members')} value={number(totals.newMembers)} />
                <StatCard label={t('hubops.dashboard.documents_verified')} value={number(totals.documentsVerified)} />
                <StatCard label={t('hubops.dashboard.walk_ins')} value={number(totals.walkIns)} />
                <StatCard label={t('hubops.dashboard.events')} value={number(totals.events)} />
                <StatCard
                    label={t('hubops.dashboard.attendance')}
                    value={t('hubops.dashboard.attendance_value', {
                        attended: totals.attended,
                        signed: totals.signedUp,
                    })}
                />
            </div>
            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardTitle>{t('hubops.dashboard.visits_chart')}</CardTitle>
                    <div className="mt-4">
                        <BarChart
                            label={t('hubops.dashboard.visits_chart')}
                            data={days.map((d) => ({
                                label: formatDate(d.date).replace(/ \d{4}$/, ''),
                                value: d.visits,
                            }))}
                        />
                    </div>
                </Card>
                <Card>
                    <CardTitle>{t('hubops.dashboard.purposes')}</CardTitle>
                    <div className="mt-4 flex flex-col gap-3">
                        {Object.entries(metrics.purposes)
                            .sort((a, b) => b[1] - a[1])
                            .map(([purpose, count]) => (
                                <ProgressBar
                                    key={purpose}
                                    value={Math.round((100 * count) / purposeTotal)}
                                    label={`${t(`hubops.purpose.${purpose}`)} - ${number(count)}`}
                                />
                            ))}
                    </div>
                </Card>
            </div>
        </HubOpsPage>
    );
}
