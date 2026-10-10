import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { Thread } from '../../components/Eligibility';

interface Props {
    referral: {
        id: string;
        stage: string;
        offer: string;
        message: string | null;
        note: string | null;
        assisted: boolean;
        sentAt: string;
        outcome: string | null;
        confirmed: boolean;
        withdrawn: boolean;
    };
    business: {
        name: string;
        stage: string;
        sector: string;
        legalForm: string | null;
        people: number;
        readiness: number | null;
        summary: Record<string, string> | null;
        contacts: { name: string; phone: string }[];
    } | null;
    documents: { id: string; type: string }[];
    timeline: { kind: string; stage: string | null; at: string }[];
    messages: { id: string; fromEmployer: boolean; body: string; held: boolean; mine: boolean; at: string }[];
    reasons: string[];
}

const OPEN = ['new', 'reviewing', 'info', 'approved'];

export default function PartnerReferral({ referral, business, documents, timeline, messages, reasons }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const base = `/partner/referrals/${referral.id}`;
    const [message, setMessage] = useState('');
    const [reason, setReason] = useState(reasons[0] ?? 'other');
    const outcome = useForm({ outcome: '', value: '' });
    const move = (stage: string) =>
        router.post(
            `${base}/move`,
            { stage, message: message || null, reason: stage === 'declined' ? reason : null },
            { preserveScroll: true, onSuccess: () => setMessage('') },
        );

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={referral.offer} />
            <Link href="/partner" className="text-primary text-sm font-semibold hover:underline">
                ← {t('partner.pipeline.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">
                        {business?.name ?? t('partner.refer.withdrawn_title')}
                    </h1>
                    <p className="text-fg-muted">
                        {referral.offer} · {formatDate(referral.sentAt)}
                        {referral.assisted && ` · ${t('partner.refer.assisted')}`}
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
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem]">
                <div className="flex flex-col gap-6">
                    {business && (
                        <Card>
                            <CardTitle>{t('partner.refer.business')}</CardTitle>
                            <p className="text-fg mt-2 text-sm">
                                {t(`start.stage.${business.stage}`)} · {t(`start.sector.${business.sector}`)}
                                {business.legalForm && ` · ${t(`start.form_name.${business.legalForm}`)}`} ·{' '}
                                {t('partner.refer.people', { count: business.people })}
                                {business.readiness !== null &&
                                    ` · ${t('start.readiness', { score: business.readiness })}`}
                            </p>
                            {referral.message && (
                                <p className="text-fg mt-3 text-sm whitespace-pre-line">"{referral.message}"</p>
                            )}
                            {referral.note && (
                                <p className="text-fg-muted mt-2 text-sm">
                                    {t('partner.refer.facilitator_note', { note: referral.note })}
                                </p>
                            )}
                            {business.summary && (
                                <dl className="mt-3 flex flex-col gap-2 text-sm">
                                    {Object.entries(business.summary).map(([k, v]) => (
                                        <div key={k}>
                                            <dt className="text-fg-muted font-semibold">{t(`partner.summary.${k}`)}</dt>
                                            <dd className="text-fg">{v}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                            <p className="text-fg mt-3 text-sm font-semibold">{t('partner.refer.contacts')}</p>
                            <ul className="text-fg text-sm">
                                {business.contacts.map((c) => (
                                    <li key={c.phone}>
                                        {c.name} · {c.phone}
                                    </li>
                                ))}
                            </ul>
                            {documents.length > 0 && (
                                <>
                                    <p className="text-fg mt-3 text-sm font-semibold">{t('partner.refer.documents')}</p>
                                    <ul className="text-sm">
                                        {documents.map((d) => (
                                            <li key={d.id}>
                                                <a
                                                    href={`${base}/documents/${d.id}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-primary hover:underline"
                                                >
                                                    {t(`documents.type.${d.type}`)}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="text-fg-muted text-xs">{t('partner.refer.documents_logged')}</p>
                                </>
                            )}
                        </Card>
                    )}
                    <Card>
                        <CardTitle>{t('partner.messages.title')}</CardTitle>
                        <div className="mt-3">
                            <Thread
                                messages={messages}
                                action={OPEN.includes(referral.stage) ? `${base}/messages` : null}
                            />
                        </div>
                    </Card>
                </div>
                <div className="flex flex-col gap-6">
                    {OPEN.includes(referral.stage) && (
                        <Card>
                            <CardTitle>{t('partner.pipeline.decide')}</CardTitle>
                            <Field label={t('partner.pipeline.message')} className="mt-3">
                                <Textarea rows={3} value={message} onChange={(e) => setMessage(e.target.value)} />
                            </Field>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {referral.stage === 'new' && (
                                    <Button size="sm" variant="secondary" onClick={() => move('reviewing')}>
                                        {t('partner.stage.reviewing')}
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    disabled={!message.trim()}
                                    onClick={() => move('info')}
                                >
                                    {t('partner.pipeline.ask_info')}
                                </Button>
                                {referral.stage !== 'approved' && (
                                    <Button size="sm" onClick={() => move('approved')}>
                                        {t('partner.pipeline.approve')}
                                    </Button>
                                )}
                            </div>
                            {referral.stage !== 'approved' && (
                                <div className="border-line mt-4 flex flex-col gap-2 border-t pt-3">
                                    <Field label={t('partner.pipeline.private_reason')}>
                                        <Select value={reason} onChange={(e) => setReason(e.target.value)}>
                                            {reasons.map((r) => (
                                                <option key={r} value={r}>
                                                    {t(`partner.reason.${r}`)}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Button size="sm" variant="ghost" onClick={() => move('declined')}>
                                        {t('partner.pipeline.decline')}
                                    </Button>
                                </div>
                            )}
                            {referral.stage === 'approved' && (
                                <form
                                    className="border-line mt-4 flex flex-col gap-2 border-t pt-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        outcome.transform((d) => ({
                                            ...d,
                                            value: d.value === '' ? null : Number(d.value),
                                        }));
                                        outcome.post(`${base}/outcome`, { preserveScroll: true });
                                    }}
                                >
                                    <Field label={t('partner.pipeline.outcome')} error={outcome.errors.outcome}>
                                        <Input
                                            value={outcome.data.outcome}
                                            onChange={(e) => outcome.setData('outcome', e.target.value)}
                                        />
                                    </Field>
                                    <Field label={t('partner.pipeline.value')}>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={outcome.data.value}
                                            onChange={(e) => outcome.setData('value', e.target.value)}
                                        />
                                    </Field>
                                    <div>
                                        <Button type="submit" size="sm" disabled={!outcome.data.outcome.trim()}>
                                            {t('partner.pipeline.record')}
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </Card>
                    )}
                    {referral.stage === 'outcome' && (
                        <Card>
                            <p className="text-fg text-sm">
                                {referral.outcome} ·{' '}
                                {referral.confirmed
                                    ? t('partner.pipeline.confirmed')
                                    : t('partner.pipeline.awaiting_confirmation')}
                            </p>
                        </Card>
                    )}
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
