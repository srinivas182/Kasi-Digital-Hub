import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Circle, Clock, MapPin, Navigation, Phone } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { cn } from '@/lib/cn';
import { formatDateTime, formatPhone } from '@/lib/format';
import { directionsUrl } from '@/lib/geo';
import { useTranslation } from '@/lib/i18n';
import { navIcon } from '@/lib/icons';

interface HomeProps {
    welcome: boolean;
    hub: {
        name: string;
        slug: string | null;
        address: string | null;
        phone: string | null;
        openingHours: Record<string, string>;
        latitude: number | null;
        longitude: number | null;
    } | null;
    steps: { key: string; title: string; description: string; href: string; done: boolean }[];
    services: {
        module: string;
        title: string;
        icon: string | null;
        href: string | null;
        group: string;
        available: boolean;
    }[];
    updates: { id: string; title: string; module: string; read: boolean; createdAt: string }[];
}

/** Personal hub home: next steps, your hub, your services and latest updates. */
export default function Home({ welcome, hub, steps, services, updates }: HomeProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const done = steps.filter((step) => step.done).length;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('nav.hub.home')} />
            <h1 className="text-fg text-2xl font-bold tracking-tight sm:text-3xl">
                {t('hub.home.title', { name: auth.user?.displayName ?? '' })}
            </h1>
            {welcome && (
                <div className="mt-4">
                    <Alert tone="success" title={t('hub.home.welcome')} />
                </div>
            )}

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle>{t('home.next_steps')}</CardTitle>
                        <span className="text-fg-muted text-sm">
                            {t('home.next_steps_progress', { done, total: steps.length })}
                        </span>
                    </div>
                    <ProgressBar
                        className="mt-3"
                        value={steps.length ? (done / steps.length) * 100 : 100}
                        label={t('home.next_steps')}
                    />
                    {done === steps.length ? (
                        <p className="text-kasi-green-ink dark:text-kasi-green mt-4 font-semibold">
                            {t('home.next_steps_done')}
                        </p>
                    ) : (
                        <ul className="mt-4 flex flex-col gap-2">
                            {steps.map((step) => (
                                <li key={step.key}>
                                    <Link
                                        href={step.href}
                                        className={cn(
                                            'rounded-control hover:bg-surface-muted flex items-start gap-3 p-2',
                                            step.done && 'opacity-70',
                                        )}
                                    >
                                        {step.done ? (
                                            <CheckCircle2
                                                className="text-kasi-green mt-0.5 size-5 shrink-0"
                                                aria-hidden
                                            />
                                        ) : (
                                            <Circle className="text-fg-muted mt-0.5 size-5 shrink-0" aria-hidden />
                                        )}
                                        <span className="flex-1">
                                            <span
                                                className={cn(
                                                    'text-fg block text-sm font-semibold',
                                                    step.done && 'line-through',
                                                )}
                                            >
                                                {step.title}
                                                <span className="sr-only">{step.done ? ' (done)' : ''}</span>
                                            </span>
                                            {!step.done && (
                                                <span className="text-fg-muted block text-sm">{step.description}</span>
                                            )}
                                        </span>
                                        {!step.done && (
                                            <ArrowRight className="text-primary mt-0.5 size-4 shrink-0" aria-hidden />
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card>
                    <CardTitle>{t('home.hub_card.title')}</CardTitle>
                    {hub ? (
                        <>
                            <p className="text-fg mt-2 font-semibold">{hub.name}</p>
                            <p className="text-fg-muted mt-1 text-sm">{t('home.hub_card.help')}</p>
                            <ul className="text-fg mt-3 flex flex-col gap-2 text-sm">
                                {hub.address && (
                                    <li className="flex gap-2">
                                        <MapPin className="text-fg-muted size-4 shrink-0" aria-hidden /> {hub.address}
                                    </li>
                                )}
                                {hub.phone && (
                                    <li className="flex gap-2">
                                        <Phone className="text-fg-muted size-4 shrink-0" aria-hidden />
                                        <a href={`tel:${hub.phone}`} className="text-primary hover:underline">
                                            {formatPhone(hub.phone)}
                                        </a>
                                    </li>
                                )}
                                {Object.entries(hub.openingHours).map(([days, hours]) => (
                                    <li key={days} className="flex gap-2">
                                        <Clock className="text-fg-muted size-4 shrink-0" aria-hidden />{' '}
                                        {t(`site.hub.hours.${days}`)}: {hours}
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-4 flex flex-wrap gap-2">
                                {hub.latitude !== null && hub.longitude !== null && (
                                    <a
                                        href={directionsUrl(hub.latitude, hub.longitude)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={buttonVariants({ variant: 'secondary', size: 'sm' })}
                                    >
                                        <Navigation className="size-4" aria-hidden /> {t('site.hub.directions')}
                                    </a>
                                )}
                                {hub.slug && (
                                    <Link
                                        href={`/hubs/${hub.slug}`}
                                        className={buttonVariants({ variant: 'ghost', size: 'sm' })}
                                    >
                                        {t('home.hub_card.view')}
                                    </Link>
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            <p className="text-fg-muted mt-2 text-sm">{t('home.hub_card.none')}</p>
                            <Link
                                href="/account?tab=profile"
                                className={buttonVariants({ size: 'sm', className: 'mt-4' })}
                            >
                                {t('home.hub_card.choose')}
                            </Link>
                        </>
                    )}
                </Card>
            </div>

            <section aria-labelledby="services" className="mt-8">
                <h2 id="services" className="text-fg text-lg font-bold">
                    {t('home.services')}
                </h2>
                <ul className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {services.map((service) => {
                        const Icon = navIcon(service.icon);
                        const body = (
                            <>
                                <Icon className="text-primary size-6" aria-hidden />
                                <span className="text-fg mt-2 block font-semibold">{service.title}</span>
                                {service.available ? (
                                    <span className="text-primary text-sm font-semibold">{t('home.open')}</span>
                                ) : (
                                    <Badge tone="neutral" className="mt-1">
                                        {t('home.coming_soon')}
                                    </Badge>
                                )}
                            </>
                        );
                        return (
                            <li key={service.module}>
                                {service.available && service.href ? (
                                    <Link
                                        href={service.href}
                                        className="rounded-card border-line bg-surface hover:bg-surface-muted block h-full border p-4"
                                    >
                                        {body}
                                    </Link>
                                ) : (
                                    <div className="rounded-card border-line bg-surface h-full border border-dashed p-4">
                                        {body}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            </section>

            <section aria-labelledby="updates" className="mt-8">
                <div className="flex items-center justify-between">
                    <h2 id="updates" className="text-fg text-lg font-bold">
                        {t('home.updates')}
                    </h2>
                    <Link href="/home/updates" className="text-primary text-sm font-semibold hover:underline">
                        {t('home.updates_all')}
                    </Link>
                </div>
                {updates.length === 0 ? (
                    <p className="text-fg-muted mt-3 text-sm">{t('home.updates_none')}</p>
                ) : (
                    <ul className="border-line bg-surface rounded-card mt-3 divide-y divide-(--color-line) border">
                        {updates.map((update) => (
                            <li key={update.id}>
                                <Link
                                    href={`/home/updates/${update.id}`}
                                    className="hover:bg-surface-muted flex items-center justify-between gap-3 p-4"
                                >
                                    <span className={cn('text-fg text-sm', !update.read && 'font-semibold')}>
                                        {!update.read && <span className="sr-only">Unread: </span>}
                                        {update.title}
                                    </span>
                                    <span className="text-fg-muted shrink-0 text-xs">
                                        {formatDateTime(update.createdAt)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppLayout>
    );
}
