import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/cn';

export const buttonVariants = cva(
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-control font-semibold whitespace-nowrap transition-colors disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-primary text-primary-fg hover:bg-primary-hover',
                accent: 'bg-kasi-marigold text-kasi-indigo hover:brightness-95',
                secondary: 'border border-line bg-surface text-fg hover:bg-surface-muted',
                ghost: 'text-fg hover:bg-surface-muted',
                danger: 'bg-kasi-danger text-white hover:brightness-95',
            },
            size: {
                sm: 'min-h-9 px-3 text-sm',
                md: 'px-4 text-sm',
                lg: 'min-h-12 px-6 text-base',
            },
            block: { true: 'w-full' },
        },
        defaultVariants: { variant: 'primary', size: 'md' },
    },
);

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
    loading?: boolean;
    icon?: ReactNode;
}

/** Primary action control. One primary button per screen (docs/content-guide.md). */
export function Button({ variant, size, block, loading, icon, className, children, disabled, ...props }: ButtonProps) {
    return (
        <button
            type="button"
            className={cn(buttonVariants({ variant, size, block }), className)}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : icon}
            {children}
        </button>
    );
}

export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    label: string;
    children: ReactNode;
}

/** Square button for an icon. The label is required and read by screen readers. */
export function IconButton({ label, className, children, ...props }: IconButtonProps) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            className={cn(
                'rounded-control text-fg hover:bg-surface-muted inline-grid size-11 place-items-center transition-colors',
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}
