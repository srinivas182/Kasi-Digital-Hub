import { Head, router } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { useTranslation } from '@/lib/i18n';

/** The demo/CI payment page: no money moves. */
export default function FakePay({ reference, amount }: { reference: string; amount: string }) {
    const { t } = useTranslation();
    return (
        <main id="main" className="bg-canvas flex min-h-screen items-center justify-center p-4">
            <Head title={t('commercial.fake.title')} />
            <Card className="w-full max-w-sm">
                <h1 className="text-fg text-xl font-bold">{t('commercial.fake.title')}</h1>
                <div className="mt-3">
                    <Alert tone="warning" title={t('commercial.fake.note')} />
                </div>
                <p className="text-fg mt-4 text-lg font-semibold">{amount}</p>
                <p className="text-fg-muted text-sm">{reference}</p>
                <div className="mt-4 flex gap-2">
                    <Button onClick={() => router.post(`/commercial/fake-pay/${reference}`, { outcome: 'pay' })}>
                        {t('commercial.fake.pay')}
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={() => router.post(`/commercial/fake-pay/${reference}`, { outcome: 'fail' })}
                    >
                        {t('commercial.fake.fail')}
                    </Button>
                </div>
            </Card>
        </main>
    );
}
