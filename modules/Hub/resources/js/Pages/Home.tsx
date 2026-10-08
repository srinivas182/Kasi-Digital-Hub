import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, Briefcase, Handshake, Rocket } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { buttonVariants } from '@/components/ui/Button';
import { Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

const COMING = [
    { icon: Briefcase, title: 'KasiWork', text: 'AI CV and jobs matched to you' },
    { icon: BookOpen, title: 'KasiLearn', text: 'Courses and certificates' },
    { icon: Rocket, title: 'KasiStart', text: 'Register and grow your business' },
    { icon: Handshake, title: 'KasiConnect', text: 'Mentors who help you grow' },
];

/** Hub home - Sprint 2 placeholder. Next steps, updates and live portal tiles arrive in Sprint 5. */
export default function Home({ welcome }: { welcome: boolean }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const name = auth.user?.displayName ?? '';

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('nav.hub.home')} />
            <h1 className="text-fg text-2xl font-bold tracking-tight sm:text-3xl">{t('hub.home.title', { name })}</h1>
            {welcome && (
                <div className="mt-4">
                    <Alert tone="success" title={t('hub.home.welcome')} />
                </div>
            )}
            {auth.user?.homeHub && (
                <p className="text-fg mt-2 font-semibold">{t('hub.home.your_hub', { hub: auth.user.homeHub })}</p>
            )}
            <p className="text-fg-muted mt-3 max-w-2xl">{t('hub.home.coming')}</p>
            <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {COMING.map(({ icon: Icon, title, text }) => (
                    <li key={title}>
                        <Card className="h-full">
                            <Icon className="text-primary size-6" aria-hidden />
                            <CardTitle className="mt-3">{title}</CardTitle>
                            <p className="text-fg-muted mt-1 text-sm">{text}</p>
                        </Card>
                    </li>
                ))}
            </ul>
            <Link href="/account" className={buttonVariants({ variant: 'secondary', className: 'mt-8' })}>
                {t('hub.home.account')}
            </Link>
        </AppLayout>
    );
}
