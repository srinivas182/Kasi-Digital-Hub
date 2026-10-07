import { useState, type InputHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';
import { formatPhone, normalisePhone } from '@/lib/format';

import { useField } from './form';

export interface PhoneInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value'> {
    /** E.164 value, e.g. +27724183390 (empty string when not yet valid). */
    value: string;
    onChange: (e164: string, raw: string) => void;
}

/**
 * South African phone number input. Shows a fixed +27 prefix, accepts numbers typed with
 * a leading 0, spaces or +27, and reports the E.164 value (or '' while incomplete).
 */
export function PhoneInput({ value, onChange, className, id, ...props }: PhoneInputProps) {
    const field = useField();
    const [raw, setRaw] = useState(() => (value ? formatPhone(value) : ''));

    return (
        <div
            className={cn(
                'rounded-control border-line bg-surface focus-within:border-primary flex min-h-11 items-stretch overflow-hidden border',
                field.invalid && 'border-kasi-danger',
                className,
            )}
        >
            <span className="border-line bg-surface-muted text-fg-muted grid place-items-center border-r px-3 text-sm font-semibold">
                +27
            </span>
            <input
                id={id ?? field.id}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                placeholder="072 123 4567"
                aria-describedby={[field.hintId, field.errorId].filter(Boolean).join(' ') || undefined}
                aria-invalid={field.invalid || undefined}
                className="text-fg w-full bg-transparent px-3 text-base outline-none"
                value={raw}
                onChange={(event) => {
                    const next = event.target.value;
                    setRaw(next);
                    onChange(normalisePhone(next) ?? '', next);
                }}
                onBlur={() => {
                    const normalised = normalisePhone(raw);
                    if (normalised) setRaw(formatPhone(normalised));
                }}
                {...props}
            />
        </div>
    );
}
