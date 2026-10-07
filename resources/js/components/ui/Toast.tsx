import { CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { useSyncExternalStore } from 'react';

import { cn } from '@/lib/cn';

export type ToastTone = 'info' | 'success' | 'danger';

export interface ToastMessage {
    id: number;
    tone: ToastTone;
    title: string;
    description?: string;
}

let toasts: ToastMessage[] = [];
let nextId = 1;
const listeners = new Set<() => void>();

function emit() {
    listeners.forEach((listener) => listener());
}

export function dismissToast(id: number): void {
    toasts = toasts.filter((toast) => toast.id !== id);
    emit();
}

/** Show a short notification. Announced politely to screen readers; auto-hides after 5 seconds. */
export function toast(title: string, options: { tone?: ToastTone; description?: string } = {}): number {
    const id = nextId++;
    toasts = [...toasts, { id, title, tone: options.tone ?? 'info', description: options.description }].slice(-3);
    emit();
    window.setTimeout(() => dismissToast(id), 5000);
    return id;
}

function subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

const icons = { info: Info, success: CheckCircle2, danger: XCircle };

/** Renders queued toasts. Mounted once by every layout. */
export function Toaster() {
    const items = useSyncExternalStore(
        subscribe,
        () => toasts,
        () => toasts,
    );

    return (
        <div
            aria-live="polite"
            aria-relevant="additions"
            className="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 sm:right-4 sm:bottom-4 sm:left-auto sm:items-end"
        >
            {items.map((item) => {
                const Icon = icons[item.tone];
                return (
                    <div
                        key={item.id}
                        className={cn(
                            'rounded-card border-line bg-surface-raised shadow-raised pointer-events-auto flex w-full max-w-sm items-start gap-3 border p-4',
                        )}
                    >
                        <Icon
                            className={cn('mt-0.5 size-5 shrink-0', {
                                'text-kasi-info': item.tone === 'info',
                                'text-kasi-green': item.tone === 'success',
                                'text-kasi-danger': item.tone === 'danger',
                            })}
                            aria-hidden
                        />
                        <div className="flex-1">
                            <p className="text-fg text-sm font-semibold">{item.title}</p>
                            {item.description && <p className="text-fg-muted text-sm">{item.description}</p>}
                        </div>
                        <button
                            type="button"
                            aria-label="Dismiss"
                            className="text-fg-muted hover:text-fg"
                            onClick={() => dismissToast(item.id)}
                        >
                            <X className="size-4" aria-hidden />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
