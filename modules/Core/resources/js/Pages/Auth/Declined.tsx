import { Head, Link } from '@inertiajs/react';

import { buttonVariants } from '@/components/ui/Button';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

export default function Declined({ minimumAge }: { minimumAge: number }) {
    const { t } = useTranslation();
    return (
        <AuthLayout title={t('auth.declined.title')}>
            <Head title={t('auth.declined.title')} />
            <p className="text-fg">{t('auth.declined.body', { age: minimumAge })}</p>
            <Link href="/" className={buttonVariants({ size: 'lg', block: true, className: 'mt-6' })}>
                {t('common.back_home')}
            </Link>
        </AuthLayout>
    );
}
