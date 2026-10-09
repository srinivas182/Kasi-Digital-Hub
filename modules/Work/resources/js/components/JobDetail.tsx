import { BadgeCheck, MapPin, Navigation } from 'lucide-react';

import { Badge, Card } from '@/components/ui/display';
import { formatDate } from '@/lib/format';
import { directionsUrl } from '@/lib/geo';
import { useTranslation } from '@/lib/i18n';

export interface Job {
    id: string;
    title: string;
    status: string;
    type: string;
    positions: number;
    employer: {
        name: string;
        verified: boolean;
        community: boolean;
        description: string | null;
        sector: string | null;
    };
    occupation: string | null;
    place: string | null;
    area: string | null;
    province: string | null;
    latitude: number | null;
    longitude: number | null;
    pay: string;
    hours: string | null;
    education: string | null;
    licence: string | null;
    experience: string;
    languages: string[];
    description: string;
    closesOn: string;
    postedOn: string | null;
    mustSkills: string[];
    niceSkills: string[];
    questions: string[];
}

/** The job advert itself, shared by the signed-in and public job pages. */
export function JobDetail({ job, children }: { job: Job; children?: React.ReactNode }) {
    const { t } = useTranslation();

    return (
        <Card>
            <div className="flex flex-wrap gap-2">
                <Badge tone="primary">{t(`work.type.${job.type}`)}</Badge>
                {job.employer.verified && (
                    <Badge tone="success">
                        <BadgeCheck className="size-3.5" aria-hidden /> {t('work.jobs.verified')}
                    </Badge>
                )}
                {job.employer.community && <Badge>{t('work.employer.community_badge')}</Badge>}
            </div>
            <h1 className="text-fg mt-3 text-2xl font-bold">{job.title}</h1>
            <p className="text-fg font-semibold">{job.employer.name}</p>
            <p className="text-fg-muted mt-1 flex items-center gap-1 text-sm">
                <MapPin className="size-4" aria-hidden />{' '}
                {[job.place, job.area, job.province].filter(Boolean).join(', ')}
            </p>
            <p className="text-fg mt-3 text-xl font-bold">{job.pay}</p>
            <p className="text-fg-muted text-sm">
                {[
                    job.hours,
                    t('work.jobs.positions', { count: job.positions }),
                    t('work.jobs.closes', { date: formatDate(job.closesOn) }),
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </p>
            {children && <div className="mt-4">{children}</div>}
            <p className="text-fg mt-6 whitespace-pre-line">{job.description}</p>
            <dl className="mt-6 grid gap-2 text-sm sm:grid-cols-[12rem_1fr]">
                <dt className="text-fg-muted">{t('work.listing.experience')}</dt>
                <dd className="text-fg">{t(`work.experience.${job.experience}`)}</dd>
                {job.education && (
                    <>
                        <dt className="text-fg-muted">{t('work.listing.education')}</dt>
                        <dd className="text-fg">{t(`work.education.${job.education}`)}</dd>
                    </>
                )}
                {job.licence && (
                    <>
                        <dt className="text-fg-muted">{t('work.listing.licence')}</dt>
                        <dd className="text-fg">Code {job.licence}</dd>
                    </>
                )}
                {job.languages.length > 0 && (
                    <>
                        <dt className="text-fg-muted">{t('work.listing.languages')}</dt>
                        <dd className="text-fg">{job.languages.join(', ')}</dd>
                    </>
                )}
                {job.mustSkills.length > 0 && (
                    <>
                        <dt className="text-fg-muted">{t('work.jobs.must')}</dt>
                        <dd className="text-fg">{job.mustSkills.join(', ')}</dd>
                    </>
                )}
                {job.niceSkills.length > 0 && (
                    <>
                        <dt className="text-fg-muted">{t('work.jobs.nice')}</dt>
                        <dd className="text-fg">{job.niceSkills.join(', ')}</dd>
                    </>
                )}
            </dl>
            {job.questions.length > 0 && (
                <>
                    <h2 className="text-fg mt-6 font-semibold">{t('work.jobs.questions')}</h2>
                    <ul className="text-fg mt-1 list-disc pl-5 text-sm">
                        {job.questions.map((q) => (
                            <li key={q}>{q}</li>
                        ))}
                    </ul>
                </>
            )}
            {job.employer.description && (
                <p className="text-fg-muted border-line mt-6 border-t pt-4 text-sm">{job.employer.description}</p>
            )}
            <div className="mt-4 flex flex-wrap items-center gap-4 text-sm">
                {job.latitude !== null && job.longitude !== null && (
                    <a
                        href={directionsUrl(job.latitude, job.longitude)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-primary inline-flex items-center gap-1 font-semibold hover:underline"
                    >
                        <Navigation className="size-4" aria-hidden /> {t('work.jobs.directions')}
                    </a>
                )}
                {job.postedOn && (
                    <span className="text-fg-muted">{t('work.jobs.posted', { date: formatDate(job.postedOn) })}</span>
                )}
            </div>
            <p className="text-fg-muted mt-4 text-xs">{t('work.jobs.safety')}</p>
        </Card>
    );
}
