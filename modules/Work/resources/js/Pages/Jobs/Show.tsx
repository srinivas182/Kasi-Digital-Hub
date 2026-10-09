import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type Job, JobDetail } from '../../components/JobDetail';
import { ReasonDialog } from '../../components/ReasonDialog';

export default function JobShow({ job, saved, canTakeDown }: { job: Job; saved: boolean; canTakeDown: boolean }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={job.title} />
            <div className="mx-auto max-w-3xl">
                <Link href="/work/jobs" className="text-primary text-sm font-semibold hover:underline">
                    ← {t('work.jobs.title')}
                </Link>
                {flash.status && (
                    <div className="mt-3">
                        <Alert tone="success" title={flash.status} />
                    </div>
                )}
                <div className="mt-3">
                    <JobDetail job={job}>
                        <div className="flex flex-wrap items-center gap-3">
                            {saved ? (
                                <Button
                                    variant="secondary"
                                    icon={<BookmarkCheck className="size-4" aria-hidden />}
                                    onClick={() => router.delete(`/work/jobs/${job.id}/save`, { preserveScroll: true })}
                                >
                                    {t('work.jobs.unsave')}
                                </Button>
                            ) : (
                                <Button
                                    icon={<Bookmark className="size-4" aria-hidden />}
                                    onClick={() =>
                                        router.post(`/work/jobs/${job.id}/save`, {}, { preserveScroll: true })
                                    }
                                >
                                    {t('work.jobs.save')}
                                </Button>
                            )}
                            {canTakeDown && (
                                <ReasonDialog
                                    trigger={<Button variant="danger">{t('work.jobs.take_down')}</Button>}
                                    title={t('work.jobs.take_down')}
                                    confirmLabel={t('work.jobs.take_down')}
                                    action={`/jobs/${job.id}/take-down`}
                                    danger
                                />
                            )}
                        </div>
                        <p className="text-fg-muted mt-2 text-sm">{t('work.jobs.apply_soon')}</p>
                        <Button
                            size="sm"
                            variant="ghost"
                            className="mt-2"
                            onClick={() =>
                                router.post(`/work/jobs/${job.id}/hide-employer`, {}, { preserveScroll: true })
                            }
                        >
                            {t('work.matches.hide')}
                        </Button>
                    </JobDetail>
                </div>
            </div>
        </AppLayout>
    );
}
