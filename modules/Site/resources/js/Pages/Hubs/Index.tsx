import { Head, Link } from '@inertiajs/react';
import { LocateFixed, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, EmptyState } from '@/components/ui/display';
import { SearchInput } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { distanceKm } from '@/lib/geo';
import { useTranslation } from '@/lib/i18n';

import { PageHero } from '../../components/PageHero';

export interface PublicHub {
    slug: string;
    name: string;
    place: string | null;
    city: string;
    province: string;
    status: 'live' | 'planned';
    latitude: number | null;
    longitude: number | null;
}

type Location = { latitude: number; longitude: number };

/** Find a hub: search, or sort by distance using the phone's location (never stored or sent). */
export default function HubsIndex({ hubs }: { hubs: PublicHub[] }) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [location, setLocation] = useState<Location | null>(null);
    const [state, setState] = useState<'idle' | 'locating' | 'denied'>('idle');

    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        const filtered = hubs.filter(
            (hub) => !q || [hub.name, hub.place, hub.city, hub.province].some((v) => v?.toLowerCase().includes(q)),
        );
        const withDistance = filtered.map((hub) => ({
            hub,
            km:
                location && hub.latitude !== null && hub.longitude !== null
                    ? distanceKm(location.latitude, location.longitude, hub.latitude, hub.longitude)
                    : null,
        }));
        return location ? withDistance.sort((a, b) => (a.km ?? Infinity) - (b.km ?? Infinity)) : withDistance;
    }, [hubs, query, location]);

    const locate = () => {
        if (!('geolocation' in navigator)) {
            setState('denied');
            return;
        }
        setState('locating');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocation({ latitude: position.coords.latitude, longitude: position.coords.longitude });
                setState('idle');
            },
            () => setState('denied'),
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 },
        );
    };

    return (
        <PublicLayout>
            <Head title={t('site.hubs.title')} />
            <PageHero title={t('site.hubs.title')} lead={t('site.hubs.lead')} />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <SearchInput
                        className="flex-1"
                        placeholder={t('site.hubs.search')}
                        aria-label={t('site.hubs.search')}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <div>
                        <Button
                            variant="secondary"
                            icon={<LocateFixed className="size-4" aria-hidden />}
                            onClick={locate}
                            loading={state === 'locating'}
                        >
                            {t('site.hubs.near_me')}
                        </Button>
                        <p className="text-fg-muted mt-1 max-w-xs text-xs">{t('site.hubs.near_me_hint')}</p>
                    </div>
                </div>
                {state === 'denied' && (
                    <div className="mt-4">
                        <Alert tone="info" title={t('site.hubs.location_denied')} />
                    </div>
                )}

                {results.length === 0 ? (
                    <div className="mt-8">
                        <EmptyState icon={<MapPin className="size-8" aria-hidden />} title={t('site.hubs.none')} />
                    </div>
                ) : (
                    <ul className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label={t('site.hubs.title')}>
                        {results.map(({ hub, km }) => (
                            <li key={hub.slug}>
                                <Link
                                    href={`/hubs/${hub.slug}`}
                                    className="rounded-card border-line bg-surface hover:bg-surface-muted flex h-full flex-col gap-2 border p-4"
                                >
                                    <span className="flex items-start justify-between gap-2">
                                        <span className="text-fg font-semibold">{hub.name}</span>
                                        <Badge tone={hub.status === 'live' ? 'success' : 'warning'}>
                                            {hub.status === 'live' ? t('site.hubs.open') : t('site.hubs.opening_soon')}
                                        </Badge>
                                    </span>
                                    <span className="text-fg-muted text-sm">
                                        {hub.city}, {hub.province}
                                    </span>
                                    {km !== null && (
                                        <span className="text-primary text-sm font-semibold">
                                            {t('site.hubs.distance', { km: Math.round(km) })}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </PublicLayout>
    );
}
