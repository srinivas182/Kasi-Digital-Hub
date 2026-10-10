import { Head, Link, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { Thread } from '../../components/Eligibility';

interface Props {
    referral: {
        id: string;
        stage: string;
        offer: string;
        partner: string;
        shared: { profile?: boolean; summary?: boolean; readiness?: boolean; documents?: string[] };
        message: string | null;
        outcome: string | null;
        value: string | null;
        confirmed: boolean;
        sentAt: string;
    };
    timeline: { kind: string; stage: string | null; at: string }[];
    messages: { id: string; fromEmployer: boolean; body: string; held: boolean; mine: boolean; at: string }[];
    views: { type: string; at: string }[];
    followups: { id: number; months: number }[];
}

const OPEN = ['new', 'reviewing', 'info', 'approved'];

export default function SupportReferral({ referral, timeline, messages, views, followups }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const base = `/support/referrals/${referral.id}`;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={referral.offer} />
            <Link href="/support/referrals" className="text-primary text-sm font-semibold hover:underline">
                ← {t('partner.support.my_referrals')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{referral.offer}</h1>
                    <p className="text-fg-muted">
                        {referral.partner} · {formatDate(referral.sentAt)}
                    </p>
                </div>
                <Badge>{t(`partner.stage.${referral.stage}`)}</Badge>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.referral && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.referral} />
                </div>
            )}
            {referral.stage === 'outcome' && !referral.confirmed && (
                <div className="mt-3">
                    <Alert
                        tone="info"
                        title={t('partner.confirm.question', {
                            outcome: `${referral.outcome ?? ''}${referral.value ? ` (${referral.value})` : ''}`,
                        })}
                    >
                        <div className="mt-2 flex gap-2">
                            <Button size="sm" onClick={() => router.post(`${base}/confirm`, { received: true })}>
                                {t('partner.confirm.yes')}
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => router.post(`${base}/confirm`, { received: false })}
                            >
                                {t('partner.confirm.no')}
                            </Button>
                        </div>
                    </Alert>
                </div>
            )}
            {followups.map((f) => (
                <div key={f.id} className="mt-3">
                    <Alert tone="info" title={t('partner.followup.question', { months: f.months })}>
                        <div className="mt-2 flex gap-2">
                            <Button
                                size="sm"
                                onClick={() => router.post(`/support/followups/${f.id}`, { trading: true })}
                            >
                                {t('partner.followup.yes')}
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => router.post(`/support/followups/${f.id}`, { trading: false })}
                            >
                                {t('partner.followup.no')}
                            </Button>
                        </div>
                    </Alert>
                </div>
            ))}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <CardTitle>{t('partner.messages.title')}</CardTitle>
                    <div className="mt-3">
                        <Thread
                            messages={messages}
                            action={OPEN.includes(referral.stage) ? `${base}/messages` : null}
                        />
                    </div>
                </Card>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('partner.refer.shared_title')}</CardTitle>
                        <ul className="text-fg mt-2 list-disc pl-5 text-sm">
                            <li>{t('partner.refer.item.profile')}</li>
                            {referral.shared.summary && <li>{t('partner.refer.item.summary')}</li>}
                            {referral.shared.readiness && <li>{t('partner.refer.item.readiness')}</li>}
                            {(referral.shared.documents ?? []).length > 0 && (
                                <li>
                                    {t('partner.refer.item.documents', {
                                        count: (referral.shared.documents ?? []).length,
                                    })}
                                </li>
                            )}
                        </ul>
                        <p className="text-fg mt-3 text-sm font-semibold">{t('partner.refer.views')}</p>
                        <ul className="text-fg-muted text-sm">
                            {views.map((v, i) => (
                                <li key={i}>
                                    {t(`documents.type.${v.type}`)} · {formatDateTime(v.at)}
                                </li>
                            ))}
                            {views.length === 0 && <li>{t('partner.refer.no_views')}</li>}
                        </ul>
                        {OPEN.includes(referral.stage) && (
                            <Button
                                size="sm"
                                variant="ghost"
                                className="mt-3"
                                onClick={() => router.post(`${base}/withdraw`)}
                            >
                                {t('partner.refer.withdraw')}
                            </Button>
                        )}
                    </Card>
                    <Card>
                        <CardTitle>{t('partner.timeline')}</CardTitle>
                        <ol className="mt-2 flex flex-col gap-1 text-sm">
                            {timeline.map((e, i) => (
                                <li key={i} className="text-fg">
                                    {e.stage ? t(`partner.stage.${e.stage}`) : t(`partner.event.${e.kind}`)}{' '}
                                    <span className="text-fg-muted">· {formatDateTime(e.at)}</span>
                                </li>
                            ))}
                        </ol>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
