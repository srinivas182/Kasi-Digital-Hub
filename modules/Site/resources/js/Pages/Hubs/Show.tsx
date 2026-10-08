import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, Mail, MapPin, Navigation, Phone } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { PublicLayout } from '@/layouts/PublicLayout';
import { formatPhone } from '@/lib/format';
import { directionsUrl } from '@/lib/geo';
import { useTranslation } from '@/lib/i18n';

import { PageHero } from '../../components/PageHero';

interface HubDetail {
    slug: string;
    name: string;
    description: string | null;
    place: string | null;
    city: string;
    province: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    status: 'live' | 'planned';
    openingHours: Record<string, string>;
    latitude: number | null;
    longitude: number | null;
    services: string[];
}

export default function HubShow({ hub }: { hub: HubDetail }) {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={hub.name} />
            <PageHero eyebrow={`${hub.city}, ${hub.province}`} title={hub.name} lead={hub.description ?? undefined}>
                <Badge tone={hub.status === 'live' ? 'success' : 'warning'}>
                    {hub.status === 'live' ? t('site.hubs.open') : t('site.hubs.opening_soon')}
                </Badge>
            </PageHero>
            <div className="mx-auto grid max-w-6xl gap-6 px-4 py-10 sm:px-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardTitle>{t('site.hub.services')}</CardTitle>
                    <ul className="mt-4 flex flex-col gap-2">
                        {hub.services.map((service) => (
                            <li key={service} className="text-fg flex items-center gap-2">
                                <span className="bg-kasi-green size-2 rounded-full" aria-hidden />
                                {t(`site.hub.service.${service}`)}
                            </li>
                        ))}
                    </ul>
                    <p className="text-fg-muted mt-6 text-sm">{t('site.hub.events')}</p>
                </Card>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('site.hub.contact')}</CardTitle>
                        <ul className="text-fg mt-4 flex flex-col gap-3 text-sm">
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
                            {hub.email && (
                                <li className="flex gap-2">
                                    <Mail className="text-fg-muted size-4 shrink-0" aria-hidden />
                                    <a href={`mailto:${hub.email}`} className="text-primary break-all hover:underline">
                                        {hub.email}
                                    </a>
                                </li>
                            )}
                        </ul>
                        {hub.latitude !== null && hub.longitude !== null && (
                            <a
                                href={directionsUrl(hub.latitude, hub.longitude)}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={buttonVariants({ variant: 'secondary', className: 'mt-4' })}
                            >
                                <Navigation className="size-4" aria-hidden /> {t('site.hub.directions')}
                            </a>
                        )}
                    </Card>
                    {Object.keys(hub.openingHours).length > 0 && (
                        <Card>
                            <CardTitle>{t('site.hub.hours')}</CardTitle>
                            <dl className="mt-4 flex flex-col gap-2 text-sm">
                                {Object.entries(hub.openingHours).map(([days, hours]) => (
                                    <div key={days} className="flex justify-between gap-3">
                                        <dt className="text-fg-muted flex items-center gap-2">
                                            <Clock className="size-4" aria-hidden /> {t(`site.hub.hours.${days}`)}
                                        </dt>
                                        <dd className="text-fg font-medium">{hours}</dd>
                                    </div>
                                ))}
                            </dl>
                        </Card>
                    )}
                </div>
                <Link
                    href="/hubs"
                    className="text-primary inline-flex items-center gap-1 font-semibold hover:underline lg:col-span-3"
                >
                    <ArrowLeft className="size-4" aria-hidden /> {t('site.hub.all_hubs')}
                </Link>
            </div>
        </PublicLayout>
    );
}
