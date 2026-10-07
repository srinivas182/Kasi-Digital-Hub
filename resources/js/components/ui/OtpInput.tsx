import { useRef, type ClipboardEvent, type KeyboardEvent } from 'react';

import { cn } from '@/lib/cn';

export interface OtpInputProps {
    length?: number;
    value: string;
    onChange: (value: string) => void;
    onComplete?: (value: string) => void;
    label?: string;
    invalid?: boolean;
    disabled?: boolean;
}

/**
 * One-time PIN entry: one box per digit, auto-advance, backspace to previous box,
 * paste of the full code, and SMS autofill (autocomplete="one-time-code").
 */
export function OtpInput({
    length = 6,
    value,
    onChange,
    onComplete,
    label = 'Verification code',
    invalid,
    disabled,
}: OtpInputProps) {
    const refs = useRef<(HTMLInputElement | null)[]>([]);
    const digits = Array.from({ length }, (_, i) => value[i] ?? '');

    const update = (next: string) => {
        const clean = next.replace(/\D/g, '').slice(0, length);
        onChange(clean);
        if (clean.length === length) onComplete?.(clean);
        return clean;
    };

    const setDigit = (index: number, digit: string) => {
        const chars = digits.slice();
        chars[index] = digit;
        const joined = chars.join('');
        update(joined);
        if (digit && index < length - 1) refs.current[index + 1]?.focus();
    };

    const onKeyDown = (index: number, event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Backspace' && !digits[index] && index > 0) {
            refs.current[index - 1]?.focus();
        }
        if (event.key === 'ArrowLeft' && index > 0) refs.current[index - 1]?.focus();
        if (event.key === 'ArrowRight' && index < length - 1) refs.current[index + 1]?.focus();
    };

    const onPaste = (event: ClipboardEvent<HTMLInputElement>) => {
        event.preventDefault();
        const clean = update(event.clipboardData.getData('text'));
        refs.current[Math.min(clean.length, length - 1)]?.focus();
    };

    return (
        <div role="group" aria-label={label} className="flex gap-2">
            {digits.map((digit, index) => (
                <input
                    key={index}
                    ref={(el) => {
                        refs.current[index] = el;
                    }}
                    aria-label={`${label} digit ${index + 1} of ${length}`}
                    inputMode="numeric"
                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                    maxLength={index === 0 ? length : 1}
                    disabled={disabled}
                    value={digit}
                    aria-invalid={invalid || undefined}
                    onChange={(event) => {
                        const typed = event.target.value.replace(/\D/g, '');
                        if (typed.length > 1) {
                            const clean = update(typed);
                            refs.current[Math.min(clean.length, length - 1)]?.focus();
                            return;
                        }
                        setDigit(index, typed);
                    }}
                    onKeyDown={(event) => onKeyDown(index, event)}
                    onPaste={onPaste}
                    className={cn(
                        'rounded-control border-line bg-surface text-fg focus:border-primary h-14 w-11 border text-center text-xl font-semibold sm:w-12',
                        invalid && 'border-kasi-danger',
                    )}
                />
            ))}
        </div>
    );
}
