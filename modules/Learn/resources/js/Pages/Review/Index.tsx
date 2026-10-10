import { Head, Link, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    courses: {
        id: string;
        title: string;
        provider: string;
        minAge: number;
        accreditation: boolean;
        waitingSince: string;
    }[];
    accreditations: { id: string; body: string; number: string; provider: string | null; evidenceUrl: string | null }[];
    published: { id: string; title: string; provider: string; slug: string }[];
}

export default function ReviewIndex({ courses, accreditations, published }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.review.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('learn.review.title')}</h1>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('learn.review.waiting')}</CardTitle>
                    {courses.length === 0 && <p className="text-fg-muted mt-2 text-sm">{t('learn.review.none')}</p>}
                    <ul className="mt-3 flex flex-col gap-2">
                        {courses.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-center justify-between gap-2">
                                <Link
                                    href={`/learn/review/courses/${c.id}`}
                                    className="text-primary font-semibold hover:underline"
                                >
                                    {c.title}
                                </Link>
                                <span className="flex gap-1">
                                    <span className="text-fg-muted text-sm">{c.provider}</span>
                                    {c.minAge < 18 && <Badge tone="warning">{t('learn.age.16')}</Badge>}
                                    {c.accreditation && (
                                        <Badge tone="primary">{t('learn.review.accreditation_claim')}</Badge>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card>
                    <CardTitle>{t('learn.accreditation.title')}</CardTitle>
                    {accreditations.length === 0 && (
                        <p className="text-fg-muted mt-2 text-sm">{t('learn.review.none')}</p>
                    )}
                    <ul className="mt-3 flex flex-col gap-3">
                        {accreditations.map((a) => (
                            <li key={a.id} className="text-sm">
                                <p className="text-fg font-semibold">
                                    {a.provider}: {a.body} {a.number}
                                </p>
                                {a.evidenceUrl && (
                                    <a
                                        href={a.evidenceUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary underline"
                                    >
                                        {t('learn.review.evidence')}
                                    </a>
                                )}
                                <div className="mt-2 flex gap-2">
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                `/learn/review/accreditations/${a.id}`,
                                                { decision: 'verified' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('learn.review.verify')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            const reason = window.prompt(t('learn.review.reject_reason'));
                                            if (reason)
                                                router.post(
                                                    `/learn/review/accreditations/${a.id}`,
                                                    { decision: 'rejected', reason },
                                                    { preserveScroll: true },
                                                );
                                        }}
                                    >
                                        {t('learn.review.reject')}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
            <Card className="mt-6">
                <CardTitle>{t('learn.review.published')}</CardTitle>
                <ul className="mt-3 flex flex-col gap-1 text-sm">
                    {published.map((c) => (
                        <li key={c.id} className="flex flex-wrap justify-between gap-2">
                            <Link href={`/learn/review/courses/${c.id}`} className="text-primary hover:underline">
                                {c.title}
                            </Link>
                            <span className="text-fg-muted">{c.provider}</span>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppLayout>
    );
}
