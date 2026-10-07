import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import type { ReactNode } from 'react';

/** Supplementary hint only - never put essential information in a tooltip (not reachable on touch screens). */
export function Tooltip({ content, children }: { content: string; children: ReactNode }) {
    return (
        <TooltipPrimitive.Provider delayDuration={300}>
            <TooltipPrimitive.Root>
                <TooltipPrimitive.Trigger asChild>{children}</TooltipPrimitive.Trigger>
                <TooltipPrimitive.Portal>
                    <TooltipPrimitive.Content
                        sideOffset={6}
                        className="bg-kasi-indigo z-50 rounded-md px-2.5 py-1.5 text-xs text-white"
                    >
                        {content}
                        <TooltipPrimitive.Arrow className="fill-kasi-indigo" />
                    </TooltipPrimitive.Content>
                </TooltipPrimitive.Portal>
            </TooltipPrimitive.Root>
        </TooltipPrimitive.Provider>
    );
}
