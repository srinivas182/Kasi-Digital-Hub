import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import { useId, type ReactNode } from 'react';

import { cn } from '@/lib/cn';

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
