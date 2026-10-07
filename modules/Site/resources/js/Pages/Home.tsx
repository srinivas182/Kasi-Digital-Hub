import { Head, usePage } from '@inertiajs/react';

import { Card } from '@/components/ui/display';
import { PublicLayout } from '@/layouts/PublicLayout';

export interface PortalSummary {
    name: string;
    title: string;
    description: string;
    group: 'front' | 'service' | 'operations' | 'national';
}

interface HomeProps {
    portals: PortalSummary[];
}

const GROUP_LABELS: Record<PortalSummary['group'], string> = {
    front: 'Front doors',
    service: 'Service portals',
    operations: 'Operations portals',
    national: 'National control',
};

const GROUP_ORDER: PortalSummary['group'][] = ['front', 'service', 'operations', 'national'];

/**
 * Platform shell home page. Replaced by the full public website in Sprint 5.
 */
export default function Home({ portals }: HomeProps) {
    const { platform } = usePage().props;

    return (
        <PublicLayout>
            <Head title="Welcome" />
            <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <p className="text-kasi-warning dark:text-kasi-marigold text-sm font-semibold tracking-wide uppercase">
                    {platform.fullName}
                </p>
                <h1 className="text-kasi-indigo dark:text-fg mt-2 text-4xl font-bold tracking-tight">
                    {platform.tagline}
                </h1>
                <p className="text-fg-muted mt-4 max-w-2xl">
                    Platform foundation is running. Portals below are registered modules and will come to life sprint by
                    sprint.
                </p>

                {GROUP_ORDER.map((group) => {
                    const items = portals.filter((portal) => portal.group === group);
                    if (items.length === 0) return null;
                    return (
                        <section key={group} className="mt-10" aria-labelledby={`group-${group}`}>
                            <h2 id={`group-${group}`} className="text-fg-muted text-sm font-semibold uppercase">
                                {GROUP_LABELS[group]}
                            </h2>
                            <ul className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {items.map((portal) => (
                                    <li key={portal.name}>
                                        <Card className="h-full">
                                            <p className="text-fg font-semibold">{portal.title}</p>
                                            <p className="text-fg-muted mt-1 text-sm">{portal.description}</p>
                                        </Card>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    );
                })}
                <p className="text-fg-muted mt-12 text-xs">
                    {platform.brand} v{platform.version}
                </p>
            </div>
        </PublicLayout>
    );
}
