import { Head, Link, router, usePage } from '@inertiajs/react';
import { Rocket } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { cn } from '@/lib/cn';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { MatchScore } from '../components/MatchScore';

interface Match {
    id: string;
    title: string;
    type: string;
    place: string | null;
    employer: string;
    pay: string;
    score: number;
    distanceKm: number | null;
    reasons: string[];
    gaps: string[];
}

interface Props {
    tab: string;
    person: { name: string; assisted: boolean };
    visible: boolean;
    matches: Match[];
    invitations: {
        id: string;
        status: string;
        message: string | null;
        at: string;
        listingId: string;
        title: string;
        employer: string;
    }[];
    views: { organisationId: string; employer: string; at: string }[];
    hidden: { id: string; name: string }[];
}

const TABS = ['matches', 'invitations', 'views', 'hidden'];

export default function Matches({ tab, person, visible, matches, invitations, views, hidden }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const waiting = invitations.filter((i) => i.status === 'sent').length;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.matches.title')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('work.matches.title')}</h1>
            <p className="text-fg-muted mt-1 text-sm">
                {visible ? t('work.matches.visible_on') : t('work.matches.visible_off')}{' '}
                {!person.assisted && (
                    <Link href="/account?tab=privacy" className="text-primary font-semibold hover:underline">
                        {t('work.matches.change')}
                    </Link>
                )}
            </p>
            <nav aria-label={t('work.matches.title')} className="mt-4 overflow-x-auto">
                <ul className="flex gap-2 pb-1">
                    {TABS.map((key) => (
                        <li key={key}>
                            <Link
                                href={`/work/matches?tab=${key}`}
                                aria-current={tab === key ? 'page' : undefined}
                                className={cn(
                                    'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-semibold whitespace-nowrap',
                                    tab === key
                                        ? 'bg-primary text-primary-fg border-transparent'
                                        : 'border-line text-fg hover:bg-surface-muted',
                                )}
                            >
                                {t(`work.matches.tab.${key}`)}
                                {key === 'invitations' && waiting > 0 && <Badge tone="warning">{waiting}</Badge>}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.invitation && (
                <div className="mt-4">
                    <Alert tone="danger" title={errors.invitation} />
                </div>
            )}

            <div className="mt-6">
                {tab === 'matches' &&
                    (matches.length === 0 ? (
                        <EmptyState icon={<Rocket className="size-8" aria-hidden />} title={t('work.matches.none')} />
                    ) : (
                        <ul className="grid gap-4 md:grid-cols-2" aria-label={t('work.matches.title')}>
                            {matches.map((m) => (
                                <li key={m.id}>
                                    <Card className="h-full">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <MatchScore score={m.score} />
                                            <Badge tone="primary">{t(`work.type.${m.type}`)}</Badge>
                                        </div>
                                        <Link
                                            href={`/work/jobs/${m.id}`}
                                            className="text-primary mt-2 block text-lg font-semibold hover:underline"
                                        >
                                            {m.title}
                                        </Link>
                                        <p className="text-fg text-sm">
                                            {m.employer} · {m.pay}
                                        </p>
                                        <p className="text-fg mt-3 text-sm font-semibold">{t('work.matches.why')}</p>
                                        <ul className="text-fg list-disc pl-5 text-sm">
                                            {m.reasons.map((r) => (
                                                <li key={r}>{r}</li>
                                            ))}
                                        </ul>
                                        {m.gaps.length > 0 && (
                                            <>
                                                <p className="text-danger-text mt-2 text-sm font-semibold">
                                                    {t('work.matches.gaps')}
                                                </p>
                                                <ul className="text-danger-text list-disc pl-5 text-sm">
                                                    {m.gaps.map((g) => (
                                                        <li key={g}>{g}</li>
                                                    ))}
                                                </ul>
                                            </>
                                        )}
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    ))}

                {tab === 'invitations' &&
                    (invitations.length === 0 ? (
                        <EmptyState title={t('work.matches.no_invitations')} />
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {invitations.map((i) => (
                                <li key={i.id}>
                                    <Card>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <Link
                                                    href={`/work/jobs/${i.listingId}`}
                                                    className="text-primary font-semibold hover:underline"
                                                >
                                                    {i.title}
                                                </Link>
                                                <p className="text-fg-muted text-sm">
                                                    {i.employer} · {formatDateTime(i.at)}
                                                </p>
                                            </div>
                                            <Badge
                                                tone={
                                                    i.status === 'sent'
                                                        ? 'warning'
                                                        : i.status === 'accepted'
                                                          ? 'success'
                                                          : 'neutral'
                                                }
                                            >
                                                {t(`work.matches.invite_status.${i.status}`)}
                                            </Badge>
                                        </div>
                                        {i.message && <p className="text-fg mt-2 text-sm">“{i.message}”</p>}
                                        {i.status === 'sent' && (
                                            <>
                                                <div className="mt-3 flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `/work/invitations/${i.id}`,
                                                                { accept: true },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {t('work.matches.accept')}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() =>
                                                            router.post(
                                                                `/work/invitations/${i.id}`,
                                                                { accept: false },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        {t('work.matches.decline')}
                                                    </Button>
                                                </div>
                                                <p className="text-fg-muted mt-2 text-xs">
                                                    {t('work.matches.accept_note')}
                                                </p>
                                            </>
                                        )}
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    ))}

                {tab === 'views' &&
                    (views.length === 0 ? (
                        <EmptyState title={t('work.matches.no_views')} />
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {views.map((v, i) => (
                                <li
                                    key={`${v.organisationId}-${i}`}
                                    className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                                >
                                    <span className="text-fg">
                                        <span className="font-semibold">{v.employer}</span>{' '}
                                        <span className="text-fg-muted text-sm">{formatDate(v.at)}</span>
                                    </span>
                                    {!hidden.some((h) => h.id === v.organisationId) && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    '/work/hidden',
                                                    { organisation_id: v.organisationId },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('work.matches.hide')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ))}

                {tab === 'hidden' &&
                    (hidden.length === 0 ? (
                        <EmptyState title={t('work.matches.no_hidden')} />
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {hidden.map((h) => (
                                <li
                                    key={h.id}
                                    className="border-line flex items-center justify-between gap-2 border-b pb-2"
                                >
                                    <span className="text-fg font-semibold">{h.name}</span>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => router.delete(`/work/hidden/${h.id}`, { preserveScroll: true })}
                                    >
                                        {t('work.matches.unhide')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    ))}
            </div>
        </AppLayout>
    );
}
