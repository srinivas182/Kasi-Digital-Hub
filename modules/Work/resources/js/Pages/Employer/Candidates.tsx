import { Head, Link, router, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { MatchScore } from '../../components/MatchScore';

interface Candidate {
    id: string;
    name: string;
    phone: string | null;
    place: string | null;
    headline: string | null;
    score: number;
    distanceKm: number | null;
    reasons: string[];
    invitation: string | null;
}

export default function Candidates({
    listing,
    candidates,
    invitesToday,
    inviteLimit,
}: {
    listing: { id: string; title: string; status: string };
    candidates: Candidate[];
    invitesToday: number;
    inviteLimit: number;
}) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.candidates.title')} />
            <Link
                href={`/work/employer/listings/${listing.id}/edit`}
                className="text-primary text-sm font-semibold hover:underline"
            >
                ← {listing.title}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('work.candidates.title')}</h1>
            <p className="text-fg-muted text-sm">{t('work.candidates.anonymous')}</p>
            <p className="text-fg-muted text-sm">
                {t('work.candidates.invites_today', { count: invitesToday, limit: inviteLimit })}
            </p>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.invite && (
                <div className="mt-4">
                    <Alert tone="danger" title={errors.invite} />
                </div>
            )}
            {candidates.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<Users className="size-8" aria-hidden />} title={t('work.candidates.none')} />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2" aria-label={t('work.candidates.title')}>
                    {candidates.map((c) => (
                        <li key={c.id}>
                            <Card className="h-full">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <MatchScore score={c.score} />
                                    {c.invitation && (
                                        <Badge tone={c.invitation === 'accepted' ? 'success' : 'neutral'}>
                                            {t(`work.matches.invite_status.${c.invitation}`)}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-fg mt-2 text-lg font-semibold">{c.name}</p>
                                {c.headline && <p className="text-fg text-sm">{c.headline}</p>}
                                <p className="text-fg-muted text-sm">
                                    {[c.place, c.distanceKm !== null ? `${c.distanceKm} km` : null, c.phone]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                                <ul className="text-fg mt-2 list-disc pl-5 text-sm">
                                    {c.reasons.map((r) => (
                                        <li key={r}>{r}</li>
                                    ))}
                                </ul>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Link
                                        href={`/work/employer/listings/${listing.id}/candidates/${c.id}`}
                                        className="text-primary self-center text-sm font-semibold hover:underline"
                                    >
                                        {t('work.candidates.view')}
                                    </Link>
                                    {!c.invitation && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    `/work/employer/listings/${listing.id}/candidates/${c.id}/invite`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('work.candidates.invite')}
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
