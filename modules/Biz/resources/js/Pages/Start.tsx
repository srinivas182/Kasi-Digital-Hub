import { Head, Link, usePage } from '@inertiajs/react';

import { Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

/** Shown when the person has no business yet, or KasiBiz isn't available at their hub (S28). */
export default function BizStart({ hasBusiness }: { hasBusiness: boolean }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('biz.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('biz.title')}</h1>
            <Card className="mt-4 max-w-xl">
                <p className="text-fg">{hasBusiness ? t('biz.start.not_at_hub') : t('biz.start.need_business')}</p>
                {!hasBusiness && (
                    <Link href="/start" className="text-primary mt-2 inline-block font-semibold hover:underline">
                        {t('market.open.go_start')}
                    </Link>
                )}
            </Card>
        </AppLayout>
    );
}
