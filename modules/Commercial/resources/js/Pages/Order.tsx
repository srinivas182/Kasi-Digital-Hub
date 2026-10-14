import { Head, Link, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { buttonVariants } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    order: {
        id: string;
        number: string;
        product: string;
        quantity: number;
        status: string;
        total: string;
        vat: string;
        merchant: string;
        paidAt: string | null;
        next: string | null;
    };
}

export default function OrderPage({ order }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const paid = ['paid', 'part_refunded', 'refunded'].includes(order.status);
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={order.number} />
            <div className="mx-auto max-w-lg">
                <h1 className="text-fg text-2xl font-bold">{t('commercial.order.title', { number: order.number })}</h1>
                <div className="mt-3">
                    <Alert
                        tone={paid ? 'success' : order.status === 'failed' ? 'danger' : 'info'}
                        title={t(`commercial.order.status.${order.status}`)}
                    />
                </div>
                <Card className="mt-4">
                    <p className="text-fg font-semibold">
                        {order.quantity} × {order.product}
                    </p>
                    <p className="text-fg">{t('commercial.order.total', { total: order.total })}</p>
                    {order.vat !== 'R0.00' && (
                        <p className="text-fg-muted text-sm">{t('commercial.order.vat', { vat: order.vat })}</p>
                    )}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {paid && (
                            <a
                                href={`/commercial/orders/${order.id}/receipt`}
                                className={buttonVariants({ variant: 'secondary' })}
                            >
                                {t('commercial.order.receipt')}
                            </a>
                        )}
                        {paid && order.next && (
                            <Link href={order.next} className={buttonVariants({})}>
                                {t('commercial.order.continue')}
                            </Link>
                        )}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
