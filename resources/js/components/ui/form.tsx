import { Search } from 'lucide-react';
import {
    createContext,
    useContext,
    useId,
    type InputHTMLAttributes,
    type ReactNode,
    type SelectHTMLAttributes,
    type TextareaHTMLAttributes,
} from 'react';

import { cn } from '@/lib/cn';

interface FieldContextValue {
    id: string;
    hintId?: string;
    errorId?: string;
    invalid: boolean;
}

const FieldContext = createContext<FieldContextValue | null>(null);

export function useField(): Partial<FieldContextValue> {
    return useContext(FieldContext) ?? {};
}

export interface FieldProps {
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
    className?: string;
}

/** Label + control + hint + error, wired together for screen readers. */
export function Field({ label, hint, error, required, children, className }: FieldProps) {
    const id = useId();
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;

    return (
        <FieldContext.Provider value={{ id, hintId, errorId, invalid: Boolean(error) }}>
            <div className={cn('flex flex-col gap-1.5', className)}>
                <label htmlFor={id} className="text-fg text-sm font-semibold">
                    {label}
                    {required && (
                        <span className="text-danger-text" aria-hidden>
                            {' '}
                            *
                        </span>
                    )}
                </label>
                {children}
                {hint && (
                    <p id={hintId} className="text-fg-muted text-sm">
                        {hint}
                    </p>
                )}
                {error && (
                    <p id={errorId} role="alert" className="text-danger-text text-sm font-medium">
                        {error}
                    </p>
                )}
            </div>
        </FieldContext.Provider>
    );
}

const controlClass =
    'w-full min-h-11 rounded-control border border-line bg-surface px-3 text-base text-fg placeholder:text-fg-muted/70 focus:border-primary aria-[invalid=true]:border-kasi-danger disabled:opacity-60';

function useControlProps(id?: string) {
    const field = useField();
    return {
        id: id ?? field.id,
        'aria-describedby': [field.hintId, field.errorId].filter(Boolean).join(' ') || undefined,
        'aria-invalid': field.invalid || undefined,
    };
}

export function Input({ className, id, ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return <input className={cn(controlClass, className)} {...useControlProps(id)} {...props} />;
}

export function Textarea({ className, id, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea className={cn(controlClass, 'min-h-28 py-2', className)} {...useControlProps(id)} {...props} />;
}

/** Native select: fastest and most familiar on low-end phones. */
export function Select({ className, id, children, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select className={cn(controlClass, 'pr-8', className)} {...useControlProps(id)} {...props}>
            {children}
        </select>
    );
}

export function SearchInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <div className={cn('relative', className)}>
            <Search
                className="text-fg-muted pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                aria-hidden
            />
            <input type="search" className={cn(controlClass, 'pl-9')} {...props} />
        </div>
    );
}
