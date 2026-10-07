import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

const tones = {
    info: { icon: Info, className: 'border-kasi-info/30 bg-kasi-info-soft text-kasi-info-ink' },
    success: { icon: CheckCircle2, className: 'border-kasi-green/30 bg-kasi-green-soft text-kasi-green-ink' },
    warning: { icon: AlertTriangle, className: 'border-kasi-marigold/40 bg-kasi-marigold-soft text-kasi-marigold-ink' },
    danger: { icon: XCircle, className: 'border-kasi-danger/30 bg-kasi-danger-soft text-kasi-danger-ink' },
} as const;

export interface AlertProps {
    tone?: keyof typeof tones;
    title: string;
    children?: ReactNode;
    action?: ReactNode;
}

/** Inline message. Errors should always say what to do next (docs/content-guide.md). */
export function Alert({ tone = 'info', title, children, action }: AlertProps) {
    const { icon: Icon, className } = tones[tone];
    return (
        <div
            role={tone === 'danger' ? 'alert' : 'status'}
            className={cn('rounded-card flex gap-3 border p-4', className)}
        >
            <Icon className="mt-0.5 size-5 shrink-0" aria-hidden />
            <div className="flex-1">
                <p className="font-semibold">{title}</p>
                {children && <div className="mt-1 text-sm">{children}</div>}
                {action && <div className="mt-3">{action}</div>}
            </div>
        </div>
    );
}
