import { Head, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    queues: { queue: string; n: number; oldest: number | string }[];
    failed: { id: string; queue: string; failed_at: string }[];
    jobs: { name: string; last: string | null; overdue: boolean }[];
    services: Record<string, string>;
    storage: Record<string, number>;
    backup: string | null;
    demo: boolean;
}

const formatBytes = (b: number) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.round(b / 1024)} KB`);

export default function System({ queues, failed, jobs, services, storage, backup, demo }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('system.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('system.title')}</h1>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('system.jobs')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {jobs.map((j) => (
                            <li key={j.name} className="flex justify-between gap-2">
                                <span className="text-fg font-mono">{j.name}</span>
                                <span>
                                    {j.last ? formatDateTime(j.last) : t('system.never')}{' '}
                                    <Badge tone={j.overdue ? 'danger' : 'success'}>
                                        {j.overdue ? t('system.overdue') : t('system.ok')}
                                    </Badge>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('system.queues')}</CardTitle>
                        <ul className="mt-2 text-sm">
                            {queues.map((q) => (
                                <li key={q.queue} className="text-fg flex justify-between">
                                    <span>{q.queue}</span>
                                    <span>{q.n}</span>
                                </li>
                            ))}
                            {queues.length === 0 && <li className="text-fg-muted">{t('system.queues_empty')}</li>}
                        </ul>
                        <CardTitle className="mt-4">{t('system.failed')}</CardTitle>
                        <ul className="mt-2 text-sm">
                            {failed.map((f) => (
                                <li key={f.id} className="text-fg flex items-center justify-between gap-2">
                                    <span>
                                        {f.queue} · {formatDateTime(f.failed_at)}
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(
                                                `/admin/system/failed/${f.id}/retry`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('system.retry')}
                                    </Button>
                                </li>
                            ))}
                            {failed.length === 0 && <li className="text-fg-muted">{t('system.no_failed')}</li>}
                        </ul>
                    </Card>
                    <Card>
                        <CardTitle>{t('system.services')}</CardTitle>
                        <dl className="mt-2 grid grid-cols-2 gap-1 text-sm">
                            {Object.entries(services).map(([k, v]) => (
                                <div key={k} className="contents">
                                    <dt className="text-fg-muted">{t(`system.service.${k}`)}</dt>
                                    <dd className="text-fg">
                                        <Badge
                                            tone={
                                                v === 'fake' || v === 'log' || v === 'database' ? 'warning' : 'success'
                                            }
                                        >
                                            {v}
                                        </Badge>
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <CardTitle className="mt-4">{t('system.storage')}</CardTitle>
                        <dl className="mt-2 grid grid-cols-2 gap-1 text-sm">
                            {Object.entries(storage).map(([k, v]) => (
                                <div key={k} className="contents">
                                    <dt className="text-fg-muted">{t(`system.store.${k}`)}</dt>
                                    <dd className="text-fg">{formatBytes(v)}</dd>
                                </div>
                            ))}
                        </dl>
                        <p className="text-fg-muted mt-3 text-sm">
                            {backup ? t('system.backup', { date: formatDateTime(backup) }) : t('system.no_backup')}
                        </p>
                        {demo && <p className="text-warning-text mt-1 text-sm">{t('system.demo_on')}</p>}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
