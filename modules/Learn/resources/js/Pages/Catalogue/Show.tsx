import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type Course, CourseDetail } from '../../components/CourseDetail';

export default function CatalogueShow({
    course,
    saved,
    enrolmentId,
}: {
    course: Course;
    saved: boolean;
    enrolmentId: string | null;
}) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [consent, setConsent] = useState(false);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={course.title} />
            <Link href="/learn/courses" className="text-primary text-sm font-semibold hover:underline">
                ← {t('learn.catalogue.title')}
            </Link>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-3">
                <CourseDetail course={course}>
                    {enrolmentId ? (
                        <Link href={`/learn/my/${enrolmentId}`} className={buttonVariants({})}>
                            {t('learn.catalogue.go_to_course')}
                        </Link>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {errors?.enrol && <Alert tone="danger" title={errors.enrol} />}
                            {errors?.consent && (
                                <>
                                    <Alert tone="info" title={errors.consent} />
                                    <Checkbox
                                        label={t('learn.enrol.consent_label')}
                                        checked={consent}
                                        onCheckedChange={(c) => setConsent(c === true)}
                                    />
                                </>
                            )}
                            <Button
                                onClick={() => router.post(`/learn/courses/${course.slug}/enrol`, { consent })}
                                disabled={!!errors?.consent && !consent}
                            >
                                {t('learn.catalogue.enrol')}
                            </Button>
                            {saved ? (
                                <Button
                                    variant="ghost"
                                    icon={<BookmarkCheck className="size-4" aria-hidden />}
                                    onClick={() =>
                                        router.delete(`/learn/courses/${course.slug}/save`, { preserveScroll: true })
                                    }
                                >
                                    {t('learn.catalogue.saved_badge')}
                                </Button>
                            ) : (
                                <Button
                                    variant="ghost"
                                    icon={<Bookmark className="size-4" aria-hidden />}
                                    onClick={() =>
                                        router.post(`/learn/courses/${course.slug}/save`, {}, { preserveScroll: true })
                                    }
                                >
                                    {t('learn.catalogue.save')}
                                </Button>
                            )}
                        </div>
                    )}
                </CourseDetail>
            </div>
        </AppLayout>
    );
}
