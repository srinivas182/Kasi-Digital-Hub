import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Briefcase, Handshake, MapPin, Rocket } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { PageHero } from '../components/PageHero';

interface HubSummary {
    slug: string;
    name: string;
    place: string | null;
    city: string;
    province: string;
}

interface HomeProps {
    impact: { people: number; hubs: number; organisations: number; verifiedDocuments: number };
    hubs: HubSummary[];
    hubCount: number;
}

const SERVICES = [
    { key: 'work', icon: Briefcase },
    { key: 'learn', icon: BookOpen },
    { key: 'start', icon: Rocket },
    { key: 'connect', icon: Handshake },
] as const;

const number = (value: number) => new Intl.NumberFormat('en-ZA').format(value);

/** Public home page. */
export default function Home({ impact, hubs, hubCount }: HomeProps) {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={t('site.home.eyebrow')} />
            <PageHero eyebrow={t('site.home.eyebrow')} title={t('site.home.headline')} lead={t('site.home.lead')}>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Link href="/login" className={buttonVariants({ variant: 'accent', size: 'lg' })}>
                        {t('site.home.cta')}
                    </Link>
                    <Link
                        href="/hubs"
                        className={buttonVariants({
                            variant: 'ghost',
                            size: 'lg',
                            className: 'text-white hover:bg-white/10',
                        })}
                    >
                        <MapPin className="size-5" aria-hidden /> {t('site.home.find_hub')}
                    </Link>
                </div>
                <p className="mt-3 text-sm text-white/75">{t('site.home.cta_hint')}</p>
            </PageHero>

            <section aria-labelledby="what" className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                <h2 id="what" className="text-fg text-2xl font-bold tracking-tight">
                    {t('site.home.what_title')}
                </h2>
                <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {SERVICES.map(({ key, icon: Icon }) => (
                        <li key={key}>
                            <Card className="h-full">
                                <span className="bg-primary-soft text-primary grid size-11 place-items-center rounded-xl">
                                    <Icon className="size-6" aria-hidden />
                                </span>
                                <h3 className="text-fg mt-4 font-semibold">{t(`site.home.what.${key}.title`)}</h3>
                                <p className="text-fg-muted mt-1 text-sm">{t(`site.home.what.${key}.body`)}</p>
                            </Card>
                        </li>
                    ))}
                </ul>
            </section>

            <section aria-labelledby="how" className="bg-surface border-line border-y">
                <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                    <h2 id="how" className="text-fg text-2xl font-bold tracking-tight">
                        {t('site.home.how_title')}
                    </h2>
                    <ol className="mt-6 grid gap-6 sm:grid-cols-3">
                        {[1, 2, 3].map((step) => (
                            <li key={step} className="flex gap-4">
                                <span
                                    className="bg-kasi-marigold text-kasi-indigo grid size-10 shrink-0 place-items-center rounded-full font-bold"
                                    aria-hidden
                                >
                                    {step}
                                </span>
                                <div>
                                    <h3 className="text-fg font-semibold">{t(`site.home.how.${step}.title`)}</h3>
                                    <p className="text-fg-muted mt-1 text-sm">{t(`site.home.how.${step}.body`)}</p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            <section aria-labelledby="impact" className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                <h2 id="impact" className="text-fg text-2xl font-bold tracking-tight">
                    {t('site.home.impact_title')}
                </h2>
                <dl className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {(
                        [
                            ['people', impact.people],
                            ['hubs', impact.hubs],
                            ['organisations', impact.organisations],
                            ['documents', impact.verifiedDocuments],
                        ] as const
                    ).map(([key, value]) => (
                        <div key={key} className="rounded-card border-line bg-surface border p-5">
                            <dd className="text-kasi-indigo dark:text-fg text-3xl font-bold">{number(value)}</dd>
                            <dt className="text-fg-muted mt-1 text-sm">{t(`site.home.impact.${key}`)}</dt>
                        </div>
                    ))}
                </dl>
            </section>

            <section aria-labelledby="hubs" className="bg-surface border-line border-y">
                <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <h2 id="hubs" className="text-fg text-2xl font-bold tracking-tight">
                            {t('site.home.hubs_title')}
                        </h2>
                        <Link
                            href="/hubs"
                            className="text-primary inline-flex items-center gap-1 font-semibold hover:underline"
                        >
                            {t('site.home.hubs_all', { count: hubCount })} <ArrowRight className="size-4" aria-hidden />
                        </Link>
                    </div>
                    <ul className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {hubs.map((hub) => (
                            <li key={hub.slug}>
                                <Link
                                    href={`/hubs/${hub.slug}`}
                                    className="rounded-card border-line hover:bg-surface-muted flex items-center gap-3 border p-4"
                                >
                                    <MapPin className="text-primary size-5 shrink-0" aria-hidden />
                                    <span>
                                        <span className="text-fg block font-semibold">{hub.name}</span>
                                        <span className="text-fg-muted block text-sm">
                                            {hub.city}, {hub.province}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            <section
                aria-labelledby="partners"
                className="mx-auto grid max-w-6xl gap-6 px-4 py-14 sm:px-6 lg:grid-cols-2"
            >
                <div>
                    <h2 id="partners" className="text-fg text-2xl font-bold tracking-tight">
                        {t('site.home.partners_title')}
                    </h2>
                    <p className="text-fg-muted mt-2">{t('site.home.partners_body')}</p>
                    <div className="mt-6 flex flex-wrap gap-3">
                        <Link href="/employers" className={buttonVariants({ variant: 'secondary' })}>
                            {t('site.nav.employers')}
                        </Link>
                        <Link href="/funders" className={buttonVariants({ variant: 'secondary' })}>
                            {t('site.nav.funders')}
                        </Link>
                    </div>
                </div>
                <div className="rounded-card bg-kasi-marigold-soft p-8">
                    <h2 className="text-kasi-indigo text-2xl font-bold">{t('site.home.final_title')}</h2>
                    <p className="text-kasi-marigold-ink mt-2">{t('site.home.final_body')}</p>
                    <Link href="/login" className={buttonVariants({ size: 'lg', className: 'mt-6' })}>
                        {t('site.home.cta')}
                    </Link>
                </div>
            </section>
        </PublicLayout>
    );
}
