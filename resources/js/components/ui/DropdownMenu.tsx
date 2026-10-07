import * as DropdownPrimitive from '@radix-ui/react-dropdown-menu';
import type { ReactNode } from 'react';

import { cn } from '@/lib/cn';

export interface MenuItem {
    label: string;
    onSelect: () => void;
    icon?: ReactNode;
    danger?: boolean;
}

export function DropdownMenu({ trigger, items, label }: { trigger: ReactNode; items: MenuItem[]; label: string }) {
    return (
        <DropdownPrimitive.Root>
            <DropdownPrimitive.Trigger asChild aria-label={label}>
                {trigger}
            </DropdownPrimitive.Trigger>
            <DropdownPrimitive.Portal>
                <DropdownPrimitive.Content
                    align="end"
                    sideOffset={6}
                    className="rounded-card border-line bg-surface-raised shadow-raised z-50 min-w-48 border p-1"
                >
                    {items.map((item) => (
                        <DropdownPrimitive.Item
                            key={item.label}
                            onSelect={item.onSelect}
                            className={cn(
                                'data-[highlighted]:bg-surface-muted flex min-h-10 cursor-pointer items-center gap-2 rounded-md px-3 text-sm outline-none',
                                item.danger ? 'text-danger-text' : 'text-fg',
                            )}
                        >
                            {item.icon}
                            {item.label}
                        </DropdownPrimitive.Item>
                    ))}
                </DropdownPrimitive.Content>
            </DropdownPrimitive.Portal>
        </DropdownPrimitive.Root>
    );
}
