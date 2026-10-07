import { useEffect, useId, useRef, useState, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface DisclosureProps {
    /** Accessible name for the trigger button. */
    label: string;
    trigger: ReactNode;
    triggerClassName?: string;
    panelClassName?: string;
    align?: 'start' | 'end';
    children: (close: () => void) => ReactNode;
}

/**
 * Lightweight show/hide panel for app-bar menus (portal switcher, user menu).
 * No positioning library - keeps every signed-in page light on low-end phones.
 * Escape and clicks outside close it; focus returns to the trigger.
 */
export function Disclosure({
    label,
    trigger,
    triggerClassName,
    panelClassName,
    align = 'end',
    children,
}: DisclosureProps) {
    const [open, setOpen] = useState(false);
    const id = useId();
    const root = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (!open) return;
        const onPointer = (event: PointerEvent) => {
            if (root.current && !root.current.contains(event.target as Node)) setOpen(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                button.current?.focus();
            }
        };
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div ref={root} className="relative">
            <button
                ref={button}
                type="button"
                aria-label={label}
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen((value) => !value)}
                className={triggerClassName}
            >
                {trigger}
            </button>
            {open && (
                <div
                    id={id}
                    className={cn(
                        'border-line bg-surface-raised text-fg shadow-raised rounded-card absolute top-full z-50 mt-2 border p-3',
                        align === 'end' ? 'right-0' : 'left-0',
                        panelClassName,
                    )}
                >
                    {children(() => setOpen(false))}
                </div>
            )}
        </div>
    );
}
