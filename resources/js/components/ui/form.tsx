import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import * as SwitchPrimitive from '@radix-ui/react-switch';
import { Check, Search } from 'lucide-react';
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

export interface CheckboxProps extends CheckboxPrimitive.CheckboxProps {
    label: ReactNode;
}

export function Checkbox({ label, id, className, ...props }: CheckboxProps) {
    const generated = useId();
    const checkboxId = id ?? generated;
    return (
        <div className={cn('flex items-start gap-3', className)}>
            <CheckboxPrimitive.Root
                id={checkboxId}
                className="border-line bg-surface data-[state=checked]:border-primary data-[state=checked]:bg-primary mt-0.5 grid size-6 shrink-0 place-items-center rounded-md border-2"
                {...props}
            >
                <CheckboxPrimitive.Indicator>
                    <Check className="text-primary-fg size-4" aria-hidden />
                </CheckboxPrimitive.Indicator>
            </CheckboxPrimitive.Root>
            <label htmlFor={checkboxId} className="text-fg text-sm leading-6">
                {label}
            </label>
        </div>
    );
}

export interface RadioOption {
    value: string;
    label: string;
    hint?: string;
}

export interface RadioGroupProps extends RadioGroupPrimitive.RadioGroupProps {
    options: RadioOption[];
    legend: string;
}

export function RadioGroup({ options, legend, className, ...props }: RadioGroupProps) {
    const id = useId();
    return (
        <fieldset className={className}>
            <legend className="text-fg mb-2 text-sm font-semibold">{legend}</legend>
            <RadioGroupPrimitive.Root className="flex flex-col gap-2" {...props}>
                {options.map((option) => (
                    <label
                        key={option.value}
                        htmlFor={`${id}-${option.value}`}
                        className="rounded-control border-line bg-surface has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft flex min-h-11 cursor-pointer items-start gap-3 border p-3"
                    >
                        <RadioGroupPrimitive.Item
                            id={`${id}-${option.value}`}
                            value={option.value}
                            className="border-line bg-surface data-[state=checked]:border-primary mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2"
                        >
                            <RadioGroupPrimitive.Indicator className="bg-primary size-2.5 rounded-full" />
                        </RadioGroupPrimitive.Item>
                        <span>
                            <span className="text-fg block text-sm font-semibold">{option.label}</span>
                            {option.hint && <span className="text-fg-muted block text-sm">{option.hint}</span>}
                        </span>
                    </label>
                ))}
            </RadioGroupPrimitive.Root>
        </fieldset>
    );
}

export interface SwitchProps extends SwitchPrimitive.SwitchProps {
    label: string;
}

export function Switch({ label, id, className, ...props }: SwitchProps) {
    const generated = useId();
    const switchId = id ?? generated;
    return (
        <div className={cn('flex min-h-11 items-center justify-between gap-4', className)}>
            <label htmlFor={switchId} className="text-fg text-sm">
                {label}
            </label>
            <SwitchPrimitive.Root
                id={switchId}
                className="bg-line data-[state=checked]:bg-primary relative h-7 w-12 shrink-0 rounded-full transition-colors"
                {...props}
            >
                <SwitchPrimitive.Thumb className="block size-6 translate-x-0.5 rounded-full bg-white shadow transition-transform data-[state=checked]:translate-x-[1.4rem]" />
            </SwitchPrimitive.Root>
        </div>
    );
}
