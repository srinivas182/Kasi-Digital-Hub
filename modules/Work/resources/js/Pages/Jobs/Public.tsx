import { Link } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { buttonVariants } from '@/components/ui/Button';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { type Job, JobDetail } from '../../components/JobDetail';

/** Public job page: shareable, findable by search engines, no employer contact details. */
export default function JobPublic({ job, open }: { job: Job; open: boolean }) {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <div className="mx-auto max-w-3xl px-4 py-8">
                {!open && <Alert tone="info" title={t('work.jobs.closed_public')} />}
                <div className="mt-3">
                    <JobDetail job={job}>
                        {open && (
                            <div className="flex flex-col gap-2">
                                <Link href="/login" className={buttonVariants({})}>
                                    {t('auth.phone.continue')}
                                </Link>
                                <p className="text-fg-muted text-sm">{t('work.jobs.public_cta')}</p>
                            </div>
                        )}
                    </JobDetail>
                </div>
            </div>
        </PublicLayout>
    );
}
