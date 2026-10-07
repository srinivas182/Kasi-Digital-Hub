import { Head, usePage } from '@inertiajs/react';

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
 * Sprint 0 platform shell. Replaced by the public website in Sprint 5.
 */
export default function Home({ portals }: HomeProps) {
    const { platform } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <header className="bg-[#24206B] text-white">
                <div className="mx-auto flex max-w-6xl items-center gap-3 px-6 py-5">
                    <span className="grid h-9 w-9 place-items-center rounded-lg bg-[#F5B700] text-lg font-bold text-[#24206B]">
                        K
                    </span>
                    <span className="text-xl font-bold">{platform.brand}</span>
                    {platform.demo && (
                        <span className="ml-auto rounded-full bg-[#F5B700] px-3 py-1 text-xs font-semibold text-[#24206B]">
                            Demo environment
                        </span>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-12">
                <p className="text-sm font-semibold tracking-wide text-[#B45309] uppercase">{platform.fullName}</p>
                <h1 className="mt-2 text-4xl font-bold tracking-tight text-[#24206B]">{platform.tagline}</h1>
                <p className="mt-4 max-w-2xl text-slate-600">
                    Platform foundation is running. Portals below are registered modules and will come to life sprint by
                    sprint.
                </p>

                {GROUP_ORDER.map((group) => {
                    const items = portals.filter((portal) => portal.group === group);
                    if (items.length === 0) return null;
                    return (
                        <section key={group} className="mt-10" aria-labelledby={`group-${group}`}>
                            <h2 id={`group-${group}`} className="text-sm font-semibold text-slate-500 uppercase">
                                {GROUP_LABELS[group]}
                            </h2>
                            <ul className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {items.map((portal) => (
                                    <li key={portal.name} className="rounded-xl border border-slate-200 bg-white p-5">
                                        <p className="font-semibold">{portal.title}</p>
                                        <p className="mt-1 text-sm text-slate-600">{portal.description}</p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    );
                })}
            </main>

            <footer className="border-t border-slate-200 py-6 text-center text-xs text-slate-500">
                {platform.brand} v{platform.version}
            </footer>
        </>
    );
}
