import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';

import { Badge, Card, EmptyState } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

const TONE: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'primary'> = {
    new: 'neutral',
    shortlisted: 'primary',
    interview: 'warning',
    offer: 'success',
    hired: 'success',
    unsuccessful: 'danger',
    withdrawn: 'neutral',
};

export default function ApplicationsIndex({
    applications,
}: {
    applications: { id: string; title: string; employer: string; stage: string; appliedAt: string }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.applications.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('work.applications.title')}</h1>
            {applications.length === 0 ? (
                <div className="mt-6">
                    <EmptyState
                        icon={<ClipboardList className="size-8" aria-hidden />}
                        title={t('work.applications.none')}
                    />
                </div>
            ) : (
                <ul className="mt-6 flex flex-col gap-3">
                    {applications.map((a) => (
                        <li key={a.id}>
                            <Link href={`/work/applications/${a.id}`} className="block">
                                <Card className="hover:bg-surface-muted flex flex-wrap items-center justify-between gap-2">
                                    <span>
                                        <span className="text-fg block font-semibold">{a.title}</span>
                                        <span className="text-fg-muted text-sm">
                                            {a.employer} ·{' '}
                                            {t('work.applications.applied', { date: formatDate(a.appliedAt) })}
                                        </span>
                                    </span>
                                    <Badge tone={TONE[a.stage] ?? 'neutral'}>{t(`work.stage.${a.stage}`)}</Badge>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
