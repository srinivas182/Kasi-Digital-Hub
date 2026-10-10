import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useEffect } from 'react';

import { buttonVariants } from '@/components/ui/Button';
import { Badge, Card, EmptyState, ProgressBar } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { sync } from '../../components/offline';

export default function MyLearning({
    enrolments,
}: {
    enrolments: { id: string; title: string; provider: string; progress: number; status: string }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    useEffect(() => {
        void sync();
        const onOnline = () => void sync();
        window.addEventListener('online', onOnline);
        return () => window.removeEventListener('online', onOnline);
    }, []);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.my.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('learn.my.title')}</h1>
            {enrolments.length === 0 ? (
                <div className="mt-6">
                    <EmptyState
                        icon={<BookOpen className="size-8" aria-hidden />}
                        title={t('learn.my.none')}
                        action={
                            <Link href="/learn/courses" className={buttonVariants({})}>
                                {t('learn.catalogue.title')}
                            </Link>
                        }
                    />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2">
                    {enrolments.map((e) => (
                        <li key={e.id}>
                            <Link href={`/learn/my/${e.id}`} className="block">
                                <Card className="hover:bg-surface-muted">
                                    <p className="text-fg font-semibold">{e.title}</p>
                                    <p className="text-fg-muted text-sm">{e.provider}</p>
                                    <div className="mt-3">
                                        <ProgressBar
                                            value={e.progress}
                                            label={t('learn.my.progress', { progress: e.progress })}
                                        />
                                    </div>
                                    {e.status === 'completed' && (
                                        <Badge tone="success" className="mt-2">
                                            {t('learn.my.done_badge')}
                                        </Badge>
                                    )}
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            <p className="mt-6 text-sm">
                <a href="/learn/offline" className="text-primary font-semibold hover:underline">
                    {t('learn.offline.open')}
                </a>
            </p>
        </AppLayout>
    );
}
