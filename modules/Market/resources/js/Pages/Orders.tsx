import { Head, Link, usePage } from '@inertiajs/react';

import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export default function MyOrders({
    orders,
}: {
    orders: { id: string; number: string; shop: string; status: string; total_cents: number; placed: string }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.orders.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('market.orders.title')}</h1>
            <Card className="mt-4">
                <ul className="flex flex-col gap-2">
                    {orders.map((o) => (
                        <li
                            key={o.id}
                            className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                        >
                            <Link
                                href={`/market/orders/${o.id}`}
                                className="text-primary font-semibold hover:underline"
                            >
                                {o.number} · {o.shop}
                            </Link>
                            <span className="flex items-center gap-2 text-sm">
                                <Badge>{t(`market.order.status.${o.status}`)}</Badge>
                                <span className="text-fg">{formatMoney(o.total_cents)}</span>
                                <span className="text-fg-muted">{formatDateTime(o.placed)}</span>
                            </span>
                        </li>
                    ))}
                    {orders.length === 0 && <li className="text-fg-muted">{t('market.orders.none')}</li>}
                </ul>
            </Card>
        </AppLayout>
    );
}
