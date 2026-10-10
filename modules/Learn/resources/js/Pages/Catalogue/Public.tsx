import { Link } from '@inertiajs/react';

import { buttonVariants } from '@/components/ui/Button';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { type Course, CourseDetail } from '../../components/CourseDetail';

/** Public, shareable course page (findable by search engines). */
export default function CataloguePublic({ course }: { course: Course }) {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <div className="mx-auto max-w-5xl px-4 py-8">
                <CourseDetail course={course}>
                    <Link href="/login" className={buttonVariants({})}>
                        {t('auth.phone.continue')}
                    </Link>
                    <p className="text-fg-muted mt-2 text-sm">{t('learn.catalogue.public_cta')}</p>
                </CourseDetail>
            </div>
        </PublicLayout>
    );
}
