import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { MatchScore } from '../../components/MatchScore';

interface Props {
    listing: { id: string; title: string };
    candidate: {
        id: string;
        name: string;
        phone: string | null;
        place: string | null;
        headline: string | null;
        summary: string | null;
        score: number;
        reasons: string[];
        experience: { title: string; kind: string; organisation: string | null; bullets: string[] }[];
        education: { name: string; year: number | null; inProgress: boolean; verified: boolean }[];
        skills: string[];
        languages: { language: string; level: string }[];
        invitation: string | null;
    };
}

export default function Candidate({ listing, candidate }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const invite = useForm({ message: '' });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={candidate.name} />
            <Link
                href={`/work/employer/listings/${listing.id}/candidates`}
                className="text-primary text-sm font-semibold hover:underline"
            >
                ← {t('work.candidates.title')}: {listing.title}
            </Link>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.invite && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.invite} />
                </div>
            )}
            <div className="mt-3 grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem]">
                <Card>
                    <MatchScore score={candidate.score} />
                    <h1 className="text-fg mt-2 text-2xl font-bold">{candidate.name}</h1>
                    {candidate.headline && <p className="text-primary font-semibold">{candidate.headline}</p>}
                    <p className="text-fg-muted text-sm">
                        {[candidate.place, candidate.phone].filter(Boolean).join(' · ')}
                    </p>
                    {candidate.summary && <p className="text-fg mt-3">{candidate.summary}</p>}
                    <ul className="text-fg mt-3 list-disc pl-5 text-sm">
                        {candidate.reasons.map((r) => (
                            <li key={r}>{r}</li>
                        ))}
                    </ul>
                    <h2 className="text-fg mt-5 font-bold">{t('work.steps.experience')}</h2>
                    {candidate.experience.map((e, i) => (
                        <div key={i} className="mt-2">
                            <p className="text-fg text-sm font-semibold">
                                {e.title}
                                {e.organisation ? `, ${e.organisation}` : ''}{' '}
                                <span className="text-fg-muted font-normal">({t(`work.exp.kind.${e.kind}`)})</span>
                            </p>
                            <ul className="text-fg list-disc pl-5 text-sm">
                                {e.bullets.map((b) => (
                                    <li key={b}>{b}</li>
                                ))}
                            </ul>
                        </div>
                    ))}
                    <h2 className="text-fg mt-5 font-bold">{t('work.steps.education')}</h2>
                    {candidate.education.map((e) => (
                        <p key={e.name} className="text-fg mt-1 text-sm">
                            {e.name} {e.verified && <Badge tone="success">{t('work.edu.verified')}</Badge>}{' '}
                            <span className="text-fg-muted">{e.inProgress ? t('work.edu.in_progress') : e.year}</span>
                        </p>
                    ))}
                    {candidate.skills.length > 0 && (
                        <p className="text-fg mt-4 text-sm">{candidate.skills.join(' · ')}</p>
                    )}
                    {candidate.languages.length > 0 && (
                        <p className="text-fg-muted mt-1 text-sm">
                            {candidate.languages
                                .map((l) => `${l.language} (${t(`work.lang.level.${l.level}`)})`)
                                .join(', ')}
                        </p>
                    )}
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('work.candidates.invite')}</CardTitle>
                    {candidate.invitation ? (
                        <Badge className="mt-2" tone={candidate.invitation === 'accepted' ? 'success' : 'neutral'}>
                            {t(`work.matches.invite_status.${candidate.invitation}`)}
                        </Badge>
                    ) : (
                        <form
                            className="mt-3 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                invite.post(`/work/employer/listings/${listing.id}/candidates/${candidate.id}/invite`, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Field label={t('work.candidates.message')} error={invite.errors.message}>
                                <Textarea
                                    rows={3}
                                    maxLength={300}
                                    value={invite.data.message}
                                    onChange={(e) => invite.setData('message', e.target.value)}
                                />
                            </Field>
                            <Button type="submit" loading={invite.processing}>
                                {t('work.candidates.invite')}
                            </Button>
                        </form>
                    )}
                    <p className="text-fg-muted mt-3 text-xs">{t('work.candidates.viewed_note')}</p>
                </Card>
            </div>
        </AppLayout>
    );
}
