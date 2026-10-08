import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Alert } from '@/components/ui/Alert';
import type { Crumb } from '@/components/ui/navigation';
import { ConsoleLayout } from '@/layouts/ConsoleLayout';

/** Admin console page frame: title, breadcrumbs, actions and flash message. */
export function AdminPage({
    title,
    crumbs = [],
    actions,
    children,
}: {
    title: string;
    crumbs?: Crumb[];
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { flash, auth } = usePage().props;
    return (
        <ConsoleLayout
            module="Admin"
            breadcrumbs={[{ label: 'Admin', href: '/admin' }, ...crumbs]}
            userName={auth.user?.name}
        >
            <Head title={title} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold tracking-tight">{title}</h1>
                {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
            </div>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6">{children}</div>
        </ConsoleLayout>
    );
}

export interface Paginated<T> {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
}

/** Previous / next links for server-side paginated lists. */
export function PageLinks({ page }: { page: Paginated<unknown> }) {
    if (!page.prev_page_url && !page.next_page_url) return null;
    return (
        <nav aria-label="Pagination" className="mt-4 flex items-center justify-between text-sm">
            {page.prev_page_url ? (
                <Link href={page.prev_page_url} preserveScroll className="text-primary font-semibold">
                    ← Previous
                </Link>
            ) : (
                <span />
            )}
            <span className="text-fg-muted">
                Page {page.current_page} of {page.last_page} · {page.total}
            </span>
            {page.next_page_url ? (
                <Link href={page.next_page_url} preserveScroll className="text-primary font-semibold">
                    Next →
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
