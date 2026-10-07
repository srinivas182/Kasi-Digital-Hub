import type { ImgHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';

export interface LazyImageProps extends ImgHTMLAttributes<HTMLImageElement> {
    src: string;
    alt: string;
    width: number;
    height: number;
    /** Above-the-fold images load immediately; everything else lazily. */
    priority?: boolean;
}

/** Image with fixed dimensions (no layout jumps) and lazy loading by default. alt is required. */
export function LazyImage({ priority, className, ...props }: LazyImageProps) {
    return (
        <img
            loading={priority ? 'eager' : 'lazy'}
            decoding="async"
            fetchPriority={priority ? 'high' : 'auto'}
            className={cn('bg-surface-muted h-auto max-w-full', className)}
            {...props}
        />
    );
}
