import { Head, Link, usePage } from '@inertiajs/react';

import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export default function SupportReferrals({
    referrals,
}: {
    referrals: { id: string; offer: string; partner: string; stage: string; sentAt: string }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('partner.support.my_referrals')} />
            <Link href="/support" className="text-primary text-sm font-semibold hover:underline">
                ← {t('partner.support.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('partner.support.my_referrals')}</h1>
            <ul className="mt-6 flex flex-col gap-3">
                {referrals.map((r) => (
                    <li key={r.id}>
                        <Link href={`/support/referrals/${r.id}`} className="block">
                            <Card className="hover:bg-surface-muted">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-fg font-semibold">{r.offer}</span>
                                    <Badge>{t(`partner.stage.${r.stage}`)}</Badge>
                                </div>
                                <p className="text-fg-muted text-sm">
                                    {r.partner} · {formatDate(r.sentAt)}
                                </p>
                            </Card>
                        </Link>
                    </li>
                ))}
                {referrals.length === 0 && <li className="text-fg-muted">{t('partner.support.no_referrals')}</li>}
            </ul>
        </AppLayout>
    );
}
