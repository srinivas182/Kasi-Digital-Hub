import { Head, Link, usePage } from '@inertiajs/react';

import { Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { LessonView, type SnapshotLesson } from '../../components/LessonView';

export default function CataloguePreview({
    course,
    lesson,
}: {
    course: { title: string; slug: string };
    lesson: SnapshotLesson;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const body = (
        <div className="mx-auto max-w-3xl">
            <Head title={lesson.title} />
            <Link href={`/learn/courses/${course.slug}`} className="text-primary text-sm font-semibold hover:underline">
                ← {course.title}
            </Link>
            <p className="text-fg-muted mt-1 text-sm">{t('learn.catalogue.preview_note')}</p>
            <Card className="mt-3">
                <LessonView lesson={lesson} />
            </Card>
        </div>
    );

    return auth.user ? (
        <AppLayout userName={auth.user.name}>{body}</AppLayout>
    ) : (
        <PublicLayout>
            <div className="px-4 py-8">{body}</div>
        </PublicLayout>
    );
}
