import { Head, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import type { CohortCard } from './Index';

export default function CohortApprovals({ cohorts }: { cohorts: (CohortCard & { provider: string })[] }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.cohort.approvals')} />
            <h1 className="text-fg text-2xl font-bold">{t('learn.cohort.approvals')}</h1>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <ul className="mt-6 flex flex-col gap-3">
                {cohorts.map((c) => (
                    <li key={c.id}>
                        <Card>
                            <p className="text-fg font-semibold">
                                {c.name} - {c.course}
                            </p>
                            <p className="text-fg-muted text-sm">
                                {c.provider} · {c.hub} · {formatDate(c.startsOn)} - {formatDate(c.endsOn)} ·{' '}
                                {t('learn.cohort.capacity')}: {c.capacity}
                            </p>
                            <div className="mt-3 flex gap-2">
                                <Button
                                    size="sm"
                                    onClick={() => router.post(`/learn/cohorts/${c.id}/decide`, { approve: true })}
                                >
                                    {t('learn.cohort.approve')}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        const reason = window.prompt(t('learn.cohort.decline_reason'));
                                        if (reason)
                                            router.post(`/learn/cohorts/${c.id}/decide`, { approve: false, reason });
                                    }}
                                >
                                    {t('learn.cohort.decline')}
                                </Button>
                            </div>
                        </Card>
                    </li>
                ))}
                {cohorts.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
            </ul>
        </AppLayout>
    );
}
