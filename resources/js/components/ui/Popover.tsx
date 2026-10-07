import * as PopoverPrimitive from '@radix-ui/react-popover';
import type { ReactNode } from 'react';

export function Popover({ trigger, children }: { trigger: ReactNode; children: ReactNode }) {
    return (
        <PopoverPrimitive.Root>
            <PopoverPrimitive.Trigger asChild>{trigger}</PopoverPrimitive.Trigger>
            <PopoverPrimitive.Portal>
                <PopoverPrimitive.Content
                    sideOffset={6}
                    className="rounded-card border-line bg-surface-raised text-fg shadow-raised z-50 w-72 border p-4 text-sm"
                >
                    {children}
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}
