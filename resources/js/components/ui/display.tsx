import { Check } from 'lucide-react';
import type { HTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/cn';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('rounded-card border-line bg-surface border p-5', className)} {...props} />;
}

export function CardTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
    return <h3 className={cn('text-fg text-base font-semibold', className)} {...props} />;
}

const badgeTones = {
    neutral: 'bg-surface-muted text-fg-muted',
    primary: 'bg-primary-soft text-primary',
    success: 'bg-kasi-green-soft text-kasi-green-ink',
    warning: 'bg-kasi-marigold-soft text-kasi-marigold-ink',
    danger: 'bg-kasi-danger-soft text-kasi-danger-ink',
    info: 'bg-kasi-info-soft text-kasi-info-ink',
} as const;

export type BadgeTone = keyof typeof badgeTones;

export function Badge({
    tone = 'neutral',
    className,
    children,
}: {
    tone?: BadgeTone;
    className?: string;
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                badgeTones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}

export function Avatar({ name, size = 'md' }: { name: string; size?: 'sm' | 'md' | 'lg' }) {
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
    const sizes = { sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-14 text-lg' };
    return (
        <span
            role="img"
            aria-label={name}
            className={cn(
                'bg-kasi-marigold-soft text-kasi-marigold-ink inline-grid shrink-0 place-items-center rounded-full font-bold',
                sizes[size],
            )}
        >
            {initials}
        </span>
    );
}

export function ProgressBar({ value, label, className }: { value: number; label: string; className?: string }) {
    const clamped = Math.max(0, Math.min(100, value));
    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            className={cn('bg-surface-muted h-2 w-full overflow-hidden rounded-full', className)}
        >
            <div className="bg-primary h-full rounded-full transition-[width]" style={{ width: `${clamped}%` }} />
        </div>
    );
}

export function StatCard({
    label,
    value,
    change,
    emphasis,
}: {
    label: string;
    value: string;
    change?: string;
    emphasis?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-card border p-5',
                emphasis ? 'bg-kasi-indigo border-transparent text-white' : 'border-line bg-surface',
            )}
        >
            <p className={cn('text-sm', emphasis ? 'text-white/75' : 'text-fg-muted')}>{label}</p>
            <p className="mt-1 text-3xl font-bold tracking-tight">{value}</p>
            {change && (
                <p
                    className={cn(
                        'mt-1 text-sm font-medium',
                        emphasis ? 'text-kasi-marigold' : 'text-kasi-green-ink dark:text-kasi-green',
                    )}
                >
                    {change}
                </p>
            )}
        </div>
    );
}

export interface TimelineItem {
    title: string;
    meta?: string;
    done?: boolean;
}

export function Timeline({ items }: { items: TimelineItem[] }) {
    return (
        <ol className="flex flex-col">
            {items.map((item, index) => (
                <li key={index} className="relative flex gap-3 pb-5 last:pb-0">
                    {index < items.length - 1 && (
                        <span className="bg-line absolute top-7 left-[13px] h-full w-0.5" aria-hidden />
                    )}
                    <span
                        className={cn(
                            'relative grid size-7 shrink-0 place-items-center rounded-full border-2',
                            item.done ? 'border-kasi-green bg-kasi-green text-white' : 'border-line bg-surface',
                        )}
                    >
                        {item.done && <Check className="size-4" aria-hidden />}
                    </span>
                    <div>
                        <p className="text-fg text-sm font-semibold">
                            {item.title}
                            <span className="sr-only">{item.done ? ' (done)' : ' (not done yet)'}</span>
                        </p>
                        {item.meta && <p className="text-fg-muted text-sm">{item.meta}</p>}
                    </div>
                </li>
            ))}
        </ol>
    );
}

export function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="rounded-card border-line flex flex-col items-center border border-dashed px-6 py-10 text-center">
            {icon && <div className="text-fg-muted mb-3">{icon}</div>}
            <p className="text-fg text-base font-semibold">{title}</p>
            {description && <p className="text-fg-muted mt-1 max-w-sm text-sm">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function Skeleton({ className }: { className?: string }) {
    return <div aria-hidden className={cn('bg-line/70 animate-pulse rounded-md', className)} />;
}
