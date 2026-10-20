import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Row {
    id: string;
    number: string;
    shop: string;
    status: string;
    atHub: string | null;
}

/** The hub's KasiMarket collection desk (S26). */
export default function Desk({
    expected,
    here,
    uncollected,
    point,
    canManage,
}: {
    expected: Row[];
    here: Row[];
    uncollected: Row[];
    point: { enabled: boolean; hours: string };
    canManage: boolean;
}) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [codes, setCodes] = useState<Record<string, string>>({});
    const settings = useForm({ enabled: point.enabled, hours: point.hours });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.desk.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('market.desk.title')}</h1>
            <p className="text-fg-muted">
                {point.enabled ? t('market.desk.open', { hours: point.hours || '-' }) : t('market.desk.closed')}
            </p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.desk && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.desk} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('market.desk.expected')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2 text-sm">
                        {expected.map((o) => (
                            <li key={o.id} className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-fg">
                                    {o.number} · {o.shop}
                                </span>
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            `/market/desk/orders/${o.id}/check-in`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('market.desk.check_in')}
                                </Button>
                            </li>
                        ))}
                        {expected.length === 0 && <li className="text-fg-muted">{t('market.trader_orders.none')}</li>}
                    </ul>
                </Card>
                <Card>
                    <CardTitle>{t('market.desk.here')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-3 text-sm">
                        {here.map((o) => (
                            <li key={o.id} className="flex flex-wrap items-end justify-between gap-2">
                                <span className="text-fg">
                                    {o.number} · {o.shop}
                                    {o.atHub && (
                                        <span className="text-fg-muted block">
                                            {t('market.desk.since', { date: formatDateTime(o.atHub) })}
                                        </span>
                                    )}
                                </span>
                                <span className="flex items-end gap-2">
                                    <Field label={t('market.desk.code')}>
                                        <Input
                                            inputMode="numeric"
                                            maxLength={6}
                                            className="w-28"
                                            value={codes[o.id] ?? ''}
                                            onChange={(e) => setCodes({ ...codes, [o.id]: e.target.value })}
                                        />
                                    </Field>
                                    <Button
                                        size="sm"
                                        disabled={(codes[o.id] ?? '').length !== 6}
                                        onClick={() =>
                                            router.post(
                                                `/market/desk/orders/${o.id}/hand-over`,
                                                { code: codes[o.id] },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('market.desk.hand_over')}
                                    </Button>
                                </span>
                            </li>
                        ))}
                        {here.length === 0 && <li className="text-fg-muted">{t('market.trader_orders.none')}</li>}
                    </ul>
                </Card>
                {uncollected.length > 0 && (
                    <Card>
                        <CardTitle>{t('market.desk.uncollected')}</CardTitle>
                        <ul className="mt-2 text-sm">
                            {uncollected.map((o) => (
                                <li key={o.id} className="text-fg">
                                    {o.number} · {o.shop}{' '}
                                    <Badge tone="warning">{t('market.desk.return_to_trader')}</Badge>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
                {canManage && (
                    <Card>
                        <CardTitle>{t('market.desk.settings')}</CardTitle>
                        <form
                            className="mt-2 flex flex-col gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                settings.post('/market/desk/settings', { preserveScroll: true });
                            }}
                        >
                            <Checkbox
                                label={t('market.desk.enable')}
                                checked={settings.data.enabled}
                                onCheckedChange={(c) => settings.setData('enabled', c === true)}
                            />
                            <Field label={t('market.desk.hours')}>
                                <Input
                                    value={settings.data.hours}
                                    onChange={(e) => settings.setData('hours', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" size="sm" variant="secondary">
                                    {t('work.save')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
