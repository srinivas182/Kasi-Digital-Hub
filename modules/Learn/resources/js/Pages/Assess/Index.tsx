import { Head, Link, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

type Row = { id: string; status: string; attempt: number; course: string; at: string; learner: string };

export default function AssessIndex({ waiting, recent }: { waiting: Row[]; recent: Row[] }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const list = (rows: Row[]) => (
        <ul className="mt-3 flex flex-col gap-2">
            {rows.map((r) => (
                <li
                    key={r.id}
                    className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-sm"
                >
                    <Link href={`/learn/assess/${r.id}`} className="text-primary font-semibold hover:underline">
                        {r.learner} - {r.course}
                    </Link>
                    <span className="text-fg-muted flex items-center gap-2">
                        {t('learn.assignment.attempt', { number: r.attempt })} · {formatDateTime(r.at)}
                        <Badge
                            tone={r.status === 'competent' ? 'success' : r.status === 'not_yet' ? 'danger' : 'warning'}
                        >
                            {t(`learn.assignment.status.${r.status}`)}
                        </Badge>
                    </span>
                </li>
            ))}
            {rows.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
        </ul>
    );

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.assess.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('learn.assess.title')}</h1>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <Card className="mt-6">
                <CardTitle>{t('learn.assess.waiting')}</CardTitle>
                {list(waiting)}
            </Card>
            <Card className="mt-6">
                <CardTitle>{t('learn.assess.recent')}</CardTitle>
                {list(recent)}
            </Card>
        </AppLayout>
    );
}
