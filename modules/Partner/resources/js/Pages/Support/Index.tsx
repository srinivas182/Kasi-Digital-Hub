import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, HandCoins } from 'lucide-react';

import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Field, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { type EligibilityResult, EligibilityList } from '../../components/Eligibility';

interface OfferCard {
    id: string;
    title: string;
    type: string;
    partner: string;
    verified: boolean;
    value: string | null;
    closesOn: string | null;
    eligibility: EligibilityResult | null;
}

export default function SupportIndex({
    businesses,
    businessId,
    offers,
}: {
    businesses: { id: string; name: string }[];
    businessId: string | null;
    offers: OfferCard[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const eligible = offers.filter((o) => o.eligibility?.eligible);
    const others = offers.filter((o) => !o.eligibility?.eligible);
    const card = (o: OfferCard) => (
        <li key={o.id}>
            <Card className="h-full">
                <div className="flex flex-wrap gap-1">
                    <Badge tone="primary">{t(`partner.type.${o.type}`)}</Badge>
                    {o.eligibility && (
                        <Badge tone={o.eligibility.eligible ? 'success' : 'warning'}>
                            {o.eligibility.eligible
                                ? t('partner.elig.you_qualify')
                                : t('partner.elig.meet', { met: o.eligibility.met, total: o.eligibility.total })}
                        </Badge>
                    )}
                </div>
                <Link
                    href={`/support/offers/${o.id}${businessId ? `?business=${businessId}` : ''}`}
                    className="text-primary mt-2 block text-lg font-semibold hover:underline"
                >
                    {o.title}
                </Link>
                <p className="text-fg flex items-center gap-1 text-sm">
                    {o.partner}{' '}
                    {o.verified && (
                        <BadgeCheck className="text-success-text size-4" aria-label={t('learn.catalogue.verified')} />
                    )}
                </p>
                <p className="text-fg-muted text-sm">
                    {[o.value, o.closesOn ? t('partner.closes', { date: formatDate(o.closesOn) }) : null]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {o.eligibility && !o.eligibility.eligible && (
                    <div className="mt-2">
                        <EligibilityList
                            result={{ ...o.eligibility, checks: o.eligibility.checks.filter((c) => !c.met) }}
                            businessId={businessId}
                        />
                    </div>
                )}
            </Card>
        </li>
    );

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('partner.support.title')} />
            <div className="flex flex-wrap items-end justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('partner.support.title')}</h1>
                <Link href="/support/referrals" className="text-primary font-semibold hover:underline">
                    {t('partner.support.my_referrals')}
                </Link>
            </div>
            {businesses.length > 1 && (
                <Field label={t('partner.support.for_business')} className="mt-3 max-w-sm">
                    <Select
                        value={businessId ?? ''}
                        onChange={(e) => router.get('/support', { business: e.target.value })}
                    >
                        {businesses.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
            {businesses.length === 0 && (
                <p className="text-fg mt-3">
                    {t('partner.support.no_business')}{' '}
                    <Link href="/start" className="text-primary font-semibold hover:underline">
                        {t('start.add_first')}
                    </Link>
                </p>
            )}
            {offers.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<HandCoins className="size-8" aria-hidden />} title={t('partner.support.none')} />
                </div>
            ) : (
                <>
                    {eligible.length > 0 && (
                        <section className="mt-6" aria-labelledby="qualify">
                            <h2 id="qualify" className="text-fg text-lg font-bold">
                                {t('partner.support.qualify')}
                            </h2>
                            <ul className="mt-2 grid gap-4 md:grid-cols-2">{eligible.map(card)}</ul>
                        </section>
                    )}
                    {others.length > 0 && (
                        <section className="mt-6" aria-labelledby="almost">
                            <h2 id="almost" className="text-fg text-lg font-bold">
                                {t('partner.support.almost')}
                            </h2>
                            <ul className="mt-2 grid gap-4 md:grid-cols-2">{others.map(card)}</ul>
                        </section>
                    )}
                </>
            )}
        </AppLayout>
    );
}
