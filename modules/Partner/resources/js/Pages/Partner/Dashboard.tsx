import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    partner: { id: string; name: string; status: string; agreement: boolean };
    agreementVersion: string;
    isAdmin: boolean;
    referrals: { id: string; offer: string; stage: string; sentAt: string; waitingDays: number | null }[];
    offers: { id: string; title: string; status: string; reason: string | null; closesOn: string | null }[];
    team: { userId: string; name: string | null; role: string }[];
}

export default function PartnerDashboard({ partner, agreementVersion, isAdmin, referrals, offers, team }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const add = useForm({ phone: '' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={partner.name} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{partner.name}</h1>
                <Badge tone={partner.status === 'verified' ? 'success' : 'warning'}>
                    {t(`work.employer.status.${partner.status}`)}
                </Badge>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {!partner.agreement && (
                <div className="mt-4">
                    <Alert tone="warning" title={t('partner.agreement.title')}>
                        <p className="mt-1 text-sm">{t('partner.agreement.text', { version: agreementVersion })}</p>
                        {isAdmin && (
                            <Button size="sm" className="mt-2" onClick={() => router.post('/partner/agreement')}>
                                {t('partner.agreement.accept')}
                            </Button>
                        )}
                    </Alert>
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <CardTitle>{t('partner.pipeline.title')}</CardTitle>
                    <ul className="mt-3 flex flex-col gap-2">
                        {referrals.map((r) => (
                            <li
                                key={r.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-sm"
                            >
                                <Link
                                    href={`/partner/referrals/${r.id}`}
                                    className="text-primary font-semibold hover:underline"
                                >
                                    {r.offer}
                                </Link>
                                <span className="flex items-center gap-2">
                                    {r.waitingDays !== null && r.waitingDays >= 7 && (
                                        <Badge tone="danger">
                                            {t('partner.pipeline.waiting', { days: r.waitingDays })}
                                        </Badge>
                                    )}
                                    <Badge>{t(`partner.stage.${r.stage}`)}</Badge>
                                    <span className="text-fg-muted">{formatDate(r.sentAt)}</span>
                                </span>
                            </li>
                        ))}
                        {referrals.length === 0 && (
                            <li className="text-fg-muted text-sm">{t('partner.pipeline.none')}</li>
                        )}
                    </ul>
                </Card>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('partner.offer.title')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-2 text-sm">
                            {offers.map((o) => (
                                <li key={o.id}>
                                    {isAdmin ? (
                                        <Link
                                            href={`/partner/offers/${o.id}/edit`}
                                            className="text-primary font-semibold hover:underline"
                                        >
                                            {o.title}
                                        </Link>
                                    ) : (
                                        <span className="text-fg font-semibold">{o.title}</span>
                                    )}{' '}
                                    <Badge
                                        tone={
                                            o.status === 'open'
                                                ? 'success'
                                                : o.status === 'rejected'
                                                  ? 'danger'
                                                  : 'neutral'
                                        }
                                    >
                                        {t(`partner.offer.status.${o.status}`)}
                                    </Badge>
                                    {o.reason && <p className="text-fg-muted text-xs">{o.reason}</p>}
                                </li>
                            ))}
                        </ul>
                        {isAdmin && partner.status === 'verified' && (
                            <Link
                                href="/partner/offers/create"
                                className={buttonVariants({ size: 'sm', className: 'mt-3' })}
                            >
                                {t('partner.offer.new')}
                            </Link>
                        )}
                    </Card>
                    <Card>
                        <CardTitle>{t('work.team.title')}</CardTitle>
                        <ul className="mt-2 text-sm">
                            {team.map((m) => (
                                <li key={m.userId}>
                                    {m.name} <Badge>{t(`roles.${m.role}`)}</Badge>
                                </li>
                            ))}
                        </ul>
                        {isAdmin && (
                            <form
                                className="mt-3 flex flex-col gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    add.post('/partner/team', { preserveScroll: true, onSuccess: () => add.reset() });
                                }}
                            >
                                <Field label={t('partner.team.add')} error={add.errors.phone ?? errors?.phone}>
                                    <PhoneInput
                                        value={add.data.phone}
                                        onChange={(_, raw) => add.setData('phone', raw)}
                                    />
                                </Field>
                                <div>
                                    <Button type="submit" size="sm" variant="secondary">
                                        {t('work.add')}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
