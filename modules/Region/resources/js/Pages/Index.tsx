import { Head, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Button } from '@/components/ui/Button';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

type Cell = number | string;

interface Props {
    area: { type: string; label: string };
    period: string;
    totals: Record<string, Cell>;
    units: Record<string, string>;
    hubs: {
        id: string;
        name: string;
        status: string;
        package: string;
        values: Record<string, Cell>;
        drop: boolean;
        health: Record<string, number>;
    }[];
    signals: string[];
}

const SHOWN = [
    'reach.registered',
    'hubs.visitors',
    'work.hires',
    'learn.completions',
    'start.formalised',
    'connect.mentorships',
];

export default function RegionIndex({ area, period, totals, units, hubs, signals }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const show = (key: string, v: Cell) =>
        typeof v === 'string'
            ? t('funder.fewer_than', { n: 10 })
            : units[key] === 'cents'
              ? formatMoney(v, { wholeRands: true })
              : v.toLocaleString('en-ZA');
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('region.title')} />
            <h1 className="text-fg text-2xl font-bold">
                {t('region.title')} - {area.label}
            </h1>
            <p className="text-fg-muted">{t('region.period', { period })}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {Object.entries(totals).map(([k, v]) => (
                    <li key={k}>
                        <Card>
                            <p className="text-fg-muted text-sm">{t(`insights.indicator.${k}`)}</p>
                            <p className="text-fg text-xl font-bold">{show(k, v)}</p>
                        </Card>
                    </li>
                ))}
            </ul>
            <Card className="mt-6">
                <CardTitle>{t('region.hubs')}</CardTitle>
                <div className="mt-2 overflow-x-auto" tabIndex={0} role="region" aria-label={t('region.hubs')}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-fg-muted text-left">
                                <th className="py-1">{t('region.hub')}</th>
                                {SHOWN.map((k) => (
                                    <th key={k} className="text-right">
                                        {t(`insights.indicator.${k}`)}
                                    </th>
                                ))}
                                <th>{t('region.health')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {hubs.map((h) => (
                                <tr key={h.id} className="border-line border-t align-top">
                                    <td className="text-fg py-2">
                                        {h.name} {h.drop && <Badge tone="danger">{t('region.drop')}</Badge>}
                                    </td>
                                    {SHOWN.map((k) => (
                                        <td key={k} className="text-fg text-right">
                                            {show(k, h.values[k] ?? 0)}
                                        </td>
                                    ))}
                                    <td className="text-fg-muted">
                                        {signals
                                            .filter((s) => (h.health[s] ?? 0) > 0)
                                            .map((s) => `${t(`insights.signal.${s}`)}: ${h.health[s]}`)
                                            .join(' · ') || '-'}
                                    </td>
                                    <td>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => {
                                                const message = window.prompt(
                                                    t('region.message.prompt', { hub: h.name }),
                                                );
                                                if (message)
                                                    router.post(
                                                        `/region/hubs/${h.id}/message`,
                                                        { message },
                                                        { preserveScroll: true },
                                                    );
                                            }}
                                        >
                                            {t('region.message.button')}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="text-fg-muted mt-2 text-xs">{t('region.privacy')}</p>
            </Card>
        </AppLayout>
    );
}
