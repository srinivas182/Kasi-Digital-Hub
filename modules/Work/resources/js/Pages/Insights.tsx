import { Head, usePage } from '@inertiajs/react';

import { DataTable } from '@/components/ui/DataTable';
import { Card, CardTitle, StatCard } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Row {
    dimension: string;
    group: string;
    people: number;
    withStrongMatch: number;
    invited: number;
}

const pct = (part: number, whole: number) => (whole === 0 ? '-' : `${Math.round((100 * part) / whole)}%`);

export default function Insights({
    totals,
    fairness,
    byHub,
}: {
    totals: Record<string, number>;
    fairness: Row[];
    byHub: { hub: string; matches: number; people: number }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.insights.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('work.insights.title')}</h1>
            <div className="mt-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                {(['profiles', 'visible', 'matches', 'strongMatches', 'invitations', 'accepted'] as const).map(
                    (key) => (
                        <StatCard
                            key={key}
                            label={t(`work.insights.${key === 'strongMatches' ? 'strong' : key}`)}
                            value={String(totals[key] ?? 0)}
                        />
                    ),
                )}
            </div>
            <Card className="mt-6">
                <CardTitle>{t('work.insights.fairness')}</CardTitle>
                <p className="text-fg-muted mt-1 text-sm">{t('work.insights.fairness_hint')}</p>
                <div className="mt-4">
                    <DataTable<Row>
                        caption={t('work.insights.fairness')}
                        rows={fairness}
                        rowKey={(r) => `${r.dimension}-${r.group}`}
                        columns={[
                            {
                                key: 'group',
                                header: t('work.insights.group'),
                                cell: (r) =>
                                    `${t(`work.insights.dimension.${r.dimension}`)}: ${t(`work.insights.group.${r.group}`)}`,
                            },
                            { key: 'people', header: t('work.insights.people'), cell: (r) => r.people },
                            {
                                key: 'strong',
                                header: t('work.insights.with_strong'),
                                cell: (r) => pct(r.withStrongMatch, r.people),
                            },
                            {
                                key: 'invited',
                                header: t('work.insights.invited'),
                                cell: (r) => pct(r.invited, r.people),
                            },
                        ]}
                    />
                </div>
            </Card>
            <Card className="mt-6">
                <CardTitle>{t('work.insights.by_hub')}</CardTitle>
                <ul className="mt-3 flex flex-col gap-1 text-sm">
                    {byHub.map((h) => (
                        <li key={h.hub} className="flex justify-between">
                            <span className="text-fg">{h.hub}</span>
                            <span className="text-fg-muted">
                                {h.matches} · {h.people} {t('work.insights.people').toLowerCase()}
                            </span>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppLayout>
    );
}
