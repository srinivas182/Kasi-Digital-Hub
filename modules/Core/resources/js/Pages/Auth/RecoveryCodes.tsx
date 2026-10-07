import { Head, router } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

export default function RecoveryCodes({ codes }: { codes: string[] }) {
    const { t } = useTranslation();
    return (
        <AuthLayout title={t('auth.recovery.title')} description={t('auth.recovery.description')}>
            <Head title={t('auth.recovery.title')} />
            <ul
                className="bg-surface-muted rounded-card grid grid-cols-2 gap-2 p-4 font-mono text-sm"
                aria-label={t('auth.recovery.title')}
            >
                {codes.map((code) => (
                    <li key={code} className="text-fg">
                        {code}
                    </li>
                ))}
            </ul>
            <div className="mt-6 flex flex-col gap-3">
                <Button variant="secondary" onClick={() => window.print()}>
                    {t('auth.recovery.print')}
                </Button>
                <Button size="lg" onClick={() => router.post('/two-factor/finish')}>
                    {t('auth.recovery.done')}
                </Button>
            </div>
        </AuthLayout>
    );
}
