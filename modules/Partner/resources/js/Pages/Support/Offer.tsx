import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { type EligibilityResult, EligibilityList } from '../../components/Eligibility';

interface Props {
    offer: {
        id: string;
        title: string;
        type: string;
        partner: string;
        verified: boolean;
        value: string | null;
        closesOn: string | null;
        description: string;
        documents: string[];
        partnerDescription: string | null;
    };
    businessId: string | null;
    eligibility: EligibilityResult | null;
    verifiedDocuments: { id: string; type: string }[];
    open: boolean;
    existing: string | null;
    assisted: boolean;
}

export default function SupportOffer({
    offer,
    businessId,
    eligibility,
    verifiedDocuments,
    open,
    existing,
    assisted,
}: Props) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const required = verifiedDocuments.filter((d) => offer.documents.includes(d.type));
    const form = useForm({
        business_id: businessId ?? '',
        profile: true,
        summary: true,
        readiness: true,
        documents: required.map((d) => d.id),
        message: '',
        note: '',
        consent: false,
    });
    const missingDocs = offer.documents.filter((type) => !verifiedDocuments.some((d) => d.type === type));

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={offer.title} />
            <Link href="/support" className="text-primary text-sm font-semibold hover:underline">
                ← {t('partner.support.title')}
            </Link>
            <div className="mt-2 grid gap-6 lg:grid-cols-[1fr_24rem]">
                <Card>
                    <Badge tone="primary">{t(`partner.type.${offer.type}`)}</Badge>
                    <h1 className="text-fg mt-2 text-2xl font-bold">{offer.title}</h1>
                    <p className="text-fg">{offer.partner}</p>
                    <p className="text-fg-muted text-sm">
                        {[
                            offer.value,
                            offer.closesOn ? t('partner.closes', { date: formatDate(offer.closesOn) }) : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <p className="text-fg mt-4 whitespace-pre-line">{offer.description}</p>
                    {offer.partnerDescription && (
                        <p className="text-fg-muted mt-4 text-sm">{offer.partnerDescription}</p>
                    )}
                    {eligibility && (
                        <>
                            <h2 className="text-fg mt-5 font-bold">{t('partner.elig.title')}</h2>
                            <div className="mt-2">
                                <EligibilityList result={eligibility} businessId={businessId} />
                            </div>
                        </>
                    )}
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('partner.refer.title')}</CardTitle>
                    {existing ? (
                        <p className="mt-2 text-sm">
                            <Link
                                href={`/support/referrals/${existing}`}
                                className="text-primary font-semibold hover:underline"
                            >
                                {t('partner.refer.see_existing')}
                            </Link>
                        </p>
                    ) : !open || !eligibility?.eligible ? (
                        <p className="text-fg-muted mt-2 text-sm">
                            {!open ? t('partner.refer.closed') : t('partner.refer.not_eligible')}
                        </p>
                    ) : (
                        <form
                            className="mt-3 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(`/support/offers/${offer.id}/refer`);
                            }}
                        >
                            {errors?.refer && <Alert tone="danger" title={errors.refer} />}
                            <fieldset>
                                <legend className="text-fg text-sm font-semibold">{t('partner.refer.share')}</legend>
                                <Checkbox
                                    label={t('partner.refer.item.profile')}
                                    checked={form.data.profile}
                                    disabled
                                    onCheckedChange={() => undefined}
                                />
                                <Checkbox
                                    label={t('partner.refer.item.summary')}
                                    checked={form.data.summary}
                                    onCheckedChange={(c) => form.setData('summary', c === true)}
                                />
                                <Checkbox
                                    label={t('partner.refer.item.readiness')}
                                    checked={form.data.readiness}
                                    onCheckedChange={(c) => form.setData('readiness', c === true)}
                                />
                                {verifiedDocuments.map((d) => (
                                    <Checkbox
                                        key={d.id}
                                        label={`${t(`documents.type.${d.type}`)}${offer.documents.includes(d.type) ? ` (${t('partner.refer.required')})` : ''}`}
                                        checked={form.data.documents.includes(d.id)}
                                        onCheckedChange={(c) =>
                                            form.setData(
                                                'documents',
                                                c === true
                                                    ? [...form.data.documents, d.id]
                                                    : form.data.documents.filter((x) => x !== d.id),
                                            )
                                        }
                                    />
                                ))}
                            </fieldset>
                            {missingDocs.length > 0 && (
                                <Alert
                                    tone="warning"
                                    title={t('partner.refer.missing_docs', {
                                        list: missingDocs.map((d) => t(`documents.type.${d}`)).join(', '),
                                    })}
                                />
                            )}
                            <Field label={t('partner.refer.message')}>
                                <Textarea
                                    rows={3}
                                    maxLength={1000}
                                    value={form.data.message}
                                    onChange={(e) => form.setData('message', e.target.value)}
                                />
                            </Field>
                            {assisted && (
                                <Field label={t('partner.refer.note')}>
                                    <Textarea
                                        rows={2}
                                        maxLength={1000}
                                        value={form.data.note}
                                        onChange={(e) => form.setData('note', e.target.value)}
                                    />
                                </Field>
                            )}
                            <Checkbox
                                label={t('partner.refer.consent', { partner: offer.partner })}
                                checked={form.data.consent}
                                onCheckedChange={(c) => form.setData('consent', c === true)}
                            />
                            <Button type="submit" loading={form.processing} disabled={!form.data.consent}>
                                {t('partner.refer.send')}
                            </Button>
                            <p className="text-fg-muted text-xs">{t('partner.refer.withdraw_hint')}</p>
                        </form>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
