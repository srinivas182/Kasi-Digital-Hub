import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

export default function Expired({ hub }: { hub: { name: string } }) {
    const { t } = useTranslation();

    return (
        <AuthLayout title={t('hubops.scan.expired_title')}>
            <p className="text-fg">{t('hubops.scan.expired_body', { hub: hub.name })}</p>
        </AuthLayout>
    );
}
