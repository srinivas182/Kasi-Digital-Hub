import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/cn';

export function BrandMark({ inverse, className }: { inverse?: boolean; className?: string }) {
    const { platform } = usePage().props;
    return (
        <Link
            href="/"
            className={cn(
                'flex items-center gap-2.5 font-bold',
                inverse ? 'text-white' : 'text-kasi-indigo dark:text-fg',
                className,
            )}
        >
            <span
                className="bg-kasi-marigold text-kasi-indigo grid size-9 place-items-center rounded-lg text-lg"
                aria-hidden
            >
                K
            </span>
            <span className="text-lg">{platform.brand}</span>
        </Link>
    );
}
