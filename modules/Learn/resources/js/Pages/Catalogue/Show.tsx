import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type Course, CourseDetail } from '../../components/CourseDetail';

export default function CatalogueShow({ course, saved }: { course: Course; saved: boolean }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;

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
                    <p className="text-fg-muted text-sm">{t('learn.catalogue.enrol_soon')}</p>
                    {saved ? (
                        <Button
                            className="mt-2"
                            variant="secondary"
                            icon={<BookmarkCheck className="size-4" aria-hidden />}
                            onClick={() =>
                                router.delete(`/learn/courses/${course.slug}/save`, { preserveScroll: true })
                            }
                        >
                            {t('learn.catalogue.saved_badge')}
                        </Button>
                    ) : (
                        <Button
                            className="mt-2"
                            icon={<Bookmark className="size-4" aria-hidden />}
                            onClick={() =>
                                router.post(`/learn/courses/${course.slug}/save`, {}, { preserveScroll: true })
                            }
                        >
                            {t('learn.catalogue.save')}
                        </Button>
                    )}
                </CourseDetail>
            </div>
        </AppLayout>
    );
}
