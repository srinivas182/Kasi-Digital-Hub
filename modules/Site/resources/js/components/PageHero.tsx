import type { ReactNode } from 'react';

/** Indigo page header used across the public website. */
export function PageHero({
    eyebrow,
    title,
    lead,
    children,
}: {
    eyebrow?: string;
    title: string;
    lead?: string;
    children?: ReactNode;
}) {
    return (
        <section className="bg-kasi-indigo text-white">
            <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                {eyebrow && (
                    <p className="text-kasi-marigold text-sm font-semibold tracking-wide uppercase">{eyebrow}</p>
                )}
                <h1 className="mt-2 max-w-3xl text-3xl font-bold tracking-tight sm:text-5xl">{title}</h1>
                {lead && <p className="mt-4 max-w-2xl text-lg text-white/85">{lead}</p>}
                {children && <div className="mt-8">{children}</div>}
            </div>
        </section>
    );
}
