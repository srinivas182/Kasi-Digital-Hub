import { Link } from '@inertiajs/react';
import * as TabsPrimitive from '@radix-ui/react-tabs';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface TabItem {
    value: string;
    label: string;
    content: ReactNode;
}

export function Tabs({ items, defaultValue, label }: { items: TabItem[]; defaultValue?: string; label: string }) {
    return (
        <TabsPrimitive.Root defaultValue={defaultValue ?? items[0]?.value}>
            <TabsPrimitive.List aria-label={label} className="border-line flex gap-1 overflow-x-auto border-b">
                {items.map((item) => (
                    <TabsPrimitive.Trigger
                        key={item.value}
                        value={item.value}
                        className="text-fg-muted data-[state=active]:border-primary data-[state=active]:text-fg min-h-11 shrink-0 border-b-2 border-transparent px-4 text-sm font-semibold"
                    >
                        {item.label}
                    </TabsPrimitive.Trigger>
                ))}
            </TabsPrimitive.List>
            {items.map((item) => (
                <TabsPrimitive.Content key={item.value} value={item.value} className="pt-4">
                    {item.content}
                </TabsPrimitive.Content>
            ))}
        </TabsPrimitive.Root>
    );
}

export interface Crumb {
    label: string;
    href?: string;
}

export function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav aria-label="Breadcrumb">
            <ol className="text-fg-muted flex flex-wrap items-center gap-1 text-sm">
                {items.map((item, index) => (
                    <li key={index} className="flex items-center gap-1">
                        {index > 0 && <ChevronRight className="size-4" aria-hidden />}
                        {item.href && index < items.length - 1 ? (
                            <Link href={item.href} className="hover:text-fg hover:underline">
                                {item.label}
                            </Link>
                        ) : (
                            <span
                                aria-current={index === items.length - 1 ? 'page' : undefined}
                                className="text-fg font-medium"
                            >
                                {item.label}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}

export interface PaginationProps {
    page: number;
    pages: number;
    onPageChange: (page: number) => void;
}

/** Previous / next with a plain-language position; simpler than numbered pages on small screens. */
export function Pagination({ page, pages, onPageChange }: PaginationProps) {
    const button =
        'inline-flex min-h-11 items-center gap-1 rounded-control border border-line bg-surface px-3 text-sm font-semibold text-fg disabled:opacity-40';
    return (
        <nav aria-label="Pagination" className="flex items-center justify-between gap-2">
            <button type="button" className={button} disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
                <ChevronLeft className="size-4" aria-hidden /> Previous
            </button>
            <span className="text-fg-muted text-sm">
                Page {page} of {pages}
            </span>
            <button
                type="button"
                className={cn(button)}
                disabled={page >= pages}
                onClick={() => onPageChange(page + 1)}
            >
                Next <ChevronRight className="size-4" aria-hidden />
            </button>
        </nav>
    );
}
