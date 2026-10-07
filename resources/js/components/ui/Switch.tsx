import * as SwitchPrimitive from '@radix-ui/react-switch';
import { useId } from 'react';

import { cn } from '@/lib/cn';

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
