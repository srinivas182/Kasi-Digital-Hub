import { Head, Link, usePage } from '@inertiajs/react';

import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Row {
    id: string;
    number: string;
    buyer: string;
    status: string;
    fulfilment: string;
    total_cents: number;
    placed: string;
    eta: string | null;
}

export default function TraderOrders({ orders, counts }: { orders: Row[]; counts: { new: number; active: number } }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const sections: [string, Row[]][] = [
        ['new', orders.filter((o) => o.status === 'placed')],
        ['active', orders.filter((o) => ['accepted', 'ready', 'out_for_delivery'].includes(o.status))],
        ['done', orders.filter((o) => !['placed', 'accepted', 'ready', 'out_for_delivery'].includes(o.status))],
    ];
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.trader_orders.title')} />
            <Link href="/market/shop" className="text-primary text-sm font-semibold hover:underline">
                ← {t('market.dash.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('market.trader_orders.title')}</h1>
            <p className="text-fg-muted">
                {t('market.trader_orders.summary', { new: counts.new, active: counts.active })}
            </p>
            {sections.map(([key, rows]) => (
                <section key={key} className="mt-6" aria-labelledby={`orders-${key}`}>
                    <h2 id={`orders-${key}`} className="text-fg text-lg font-bold">
                        {t(`market.trader_orders.${key}`)}
                    </h2>
                    <Card className="mt-2">
                        <ul className="flex flex-col gap-2">
                            {rows.map((o) => (
                                <li
                                    key={o.id}
                                    className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                                >
                                    <Link
                                        href={`/market/shop/orders/${o.id}`}
                                        className="text-primary font-semibold hover:underline"
                                    >
                                        {o.number} · {o.buyer}
                                    </Link>
                                    <span className="flex flex-wrap items-center gap-2 text-sm">
                                        <Badge tone="neutral">{t(`market.order.fulfilment.${o.fulfilment}`)}</Badge>
                                        <Badge>{t(`market.order.status.${o.status}`)}</Badge>
                                        <span className="text-fg">{formatMoney(o.total_cents)}</span>
                                        <span className="text-fg-muted">{o.eta ?? formatDateTime(o.placed)}</span>
                                    </span>
                                </li>
                            ))}
                            {rows.length === 0 && <li className="text-fg-muted">{t('market.trader_orders.none')}</li>}
                        </ul>
                    </Card>
                </section>
            ))}
        </AppLayout>
    );
}
