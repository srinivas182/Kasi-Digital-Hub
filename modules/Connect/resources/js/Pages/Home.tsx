import { Head, Link, router, usePage } from '@inertiajs/react';
import { Handshake } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    mentorships: { id: string; status: string; goal: string; role: string; with: string }[];
    pending: { id: string; mentor: string; goal: string }[];
    isMentor: boolean;
    minor: boolean;
}

export default function ConnectHome({ mentorships, pending, isMentor, minor }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('connect.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('connect.title')}</h1>
                <span className="flex gap-2">
                    <Link href="/connect/find" className={buttonVariants({})}>
                        {t('nav.connect.find')}
                    </Link>
                    {!minor && (
                        <Link href="/connect/mentor" className={buttonVariants({ variant: 'secondary' })}>
                            {isMentor ? t('connect.mentor.my_profile') : t('nav.connect.be_mentor')}
                        </Link>
                    )}
                </span>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {minor && (
                <div className="mt-3">
                    <Alert tone="info" title={t('connect.minor.info')} />
                </div>
            )}
            {pending.length > 0 && (
                <ul className="mt-4 flex flex-col gap-2">
                    {pending.map((p) => (
                        <li key={p.id} className="text-fg flex flex-wrap items-center gap-2 text-sm">
                            {t('connect.request.waiting', { name: p.mentor })}
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => router.post(`/connect/requests/${p.id}/withdraw`)}
                            >
                                {t('partner.refer.withdraw')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            {mentorships.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<Handshake className="size-8" aria-hidden />} title={t('connect.none')} />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2">
                    {mentorships.map((m) => (
                        <li key={m.id}>
                            <Link href={`/connect/mentorships/${m.id}`} className="block">
                                <Card className="hover:bg-surface-muted">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-fg font-semibold">
                                            {t(m.role === 'mentor' ? 'connect.you_mentor' : 'connect.your_mentor', {
                                                name: m.with,
                                            })}
                                        </span>
                                        <Badge>{t(`connect.status.${m.status}`)}</Badge>
                                    </div>
                                    <p className="text-fg-muted text-sm">{t(`connect.goal.${m.goal}`)}</p>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
