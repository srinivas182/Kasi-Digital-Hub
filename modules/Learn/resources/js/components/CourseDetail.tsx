import { BadgeCheck } from 'lucide-react';

import { Badge, Card, CardTitle } from '@/components/ui/display';
import { useTranslation } from '@/lib/i18n';

import { formatBytes } from './format';
import { Outline, type OutlineModule } from './Outline';

export interface Course {
    slug: string;
    title: string;
    summary: string | null;
    provider: string;
    verified: boolean;
    accredited: boolean;
    accreditedBy: string | null;
    topic: string;
    level: string;
    hours: number | null;
    language: string;
    delivery: string;
    dataBytes: number;
    outcomes: string[];
    prerequisites: string | null;
    minAge: number;
    nqfLevel: number | null;
    credits: number | null;
    licence: string;
    attribution: string | null;
    providerDescription: string | null;
    version: number | null;
    modules: OutlineModule[];
}

export function CourseDetail({ course, children }: { course: Course; children?: React.ReactNode }) {
    const { t } = useTranslation();
    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <Card>
                <div className="flex flex-wrap gap-1">
                    <Badge tone="primary">{t(`learn.topic.${course.topic}`)}</Badge>
                    {course.accredited && (
                        <Badge tone="success">
                            {t('learn.catalogue.accredited_by', { body: course.accreditedBy ?? '' })}
                            {course.nqfLevel ? ` · NQF ${course.nqfLevel}` : ''}
                            {course.credits ? ` · ${course.credits} ${t('learn.catalogue.credits')}` : ''}
                        </Badge>
                    )}
                </div>
                <h1 className="text-fg mt-2 text-2xl font-bold">{course.title}</h1>
                <p className="text-fg flex items-center gap-1">
                    {course.provider}{' '}
                    {course.verified && (
                        <BadgeCheck className="text-success-text size-4" aria-label={t('learn.catalogue.verified')} />
                    )}
                </p>
                {course.summary && <p className="text-fg mt-3">{course.summary}</p>}
                {course.outcomes.length > 0 && (
                    <>
                        <h2 className="text-fg mt-5 font-bold">{t('learn.catalogue.outcomes')}</h2>
                        <ul className="text-fg mt-1 list-disc pl-5">
                            {course.outcomes.map((o) => (
                                <li key={o}>{o}</li>
                            ))}
                        </ul>
                    </>
                )}
                <h2 className="text-fg mt-5 mb-2 font-bold">{t('learn.catalogue.contents')}</h2>
                <Outline modules={course.modules} slug={course.slug} />
            </Card>
            <div className="flex flex-col gap-4">
                <Card>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                        <dt className="text-fg-muted">{t('learn.author.level')}</dt>
                        <dd className="text-fg">{t(`learn.level.${course.level}`)}</dd>
                        {course.hours && (
                            <>
                                <dt className="text-fg-muted">{t('learn.author.hours')}</dt>
                                <dd className="text-fg">{course.hours}</dd>
                            </>
                        )}
                        <dt className="text-fg-muted">{t('learn.catalogue.data')}</dt>
                        <dd className="text-fg">{formatBytes(course.dataBytes)}</dd>
                        <dt className="text-fg-muted">{t('learn.author.language')}</dt>
                        <dd className="text-fg">{course.language}</dd>
                        <dt className="text-fg-muted">{t('learn.author.delivery')}</dt>
                        <dd className="text-fg">{t(`learn.delivery.${course.delivery}`)}</dd>
                        <dt className="text-fg-muted">{t('learn.author.min_age')}</dt>
                        <dd className="text-fg">{t(`learn.age.${course.minAge}`)}</dd>
                        {course.prerequisites && (
                            <>
                                <dt className="text-fg-muted">{t('learn.author.prerequisites')}</dt>
                                <dd className="text-fg">{course.prerequisites}</dd>
                            </>
                        )}
                    </dl>
                    {children && <div className="mt-4">{children}</div>}
                </Card>
                {course.providerDescription && (
                    <Card>
                        <CardTitle>{course.provider}</CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">{course.providerDescription}</p>
                    </Card>
                )}
                <p className="text-fg-muted text-xs">
                    {t(`learn.licence.${course.licence}`)}
                    {course.attribution ? ` · ${course.attribution}` : ''}
                    {course.version ? ` · ${t('learn.catalogue.version', { number: course.version })}` : ''}
                </p>
            </div>
        </div>
    );
}
