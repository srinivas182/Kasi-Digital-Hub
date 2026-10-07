import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from './Button';

interface DialogBaseProps {
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    trigger?: ReactNode;
    title: string;
    description?: string;
    children?: ReactNode;
    footer?: ReactNode;
}

/** Centred dialog on desktop. Focus is trapped and returned on close; Escape closes. */
export function Dialog({ open, onOpenChange, trigger, title, description, children, footer }: DialogBaseProps) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            {trigger && <DialogPrimitive.Trigger asChild>{trigger}</DialogPrimitive.Trigger>}
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/50" />
                <DialogPrimitive.Content className="rounded-card bg-surface-raised shadow-raised fixed top-1/2 left-1/2 z-50 w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 p-6">
                    <DialogHeader title={title} description={description} />
                    {children && <div className="text-fg mt-4 text-sm">{children}</div>}
                    {footer && (
                        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">{footer}</div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

function DialogHeader({ title, description }: { title: string; description?: string }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <div>
                <DialogPrimitive.Title className="text-fg text-lg font-semibold">{title}</DialogPrimitive.Title>
                {description ? (
                    <DialogPrimitive.Description className="text-fg-muted mt-1 text-sm">
                        {description}
                    </DialogPrimitive.Description>
                ) : (
                    <DialogPrimitive.Description className="sr-only">{title}</DialogPrimitive.Description>
                )}
            </div>
            <DialogPrimitive.Close
                className="rounded-control text-fg-muted hover:bg-surface-muted grid size-9 shrink-0 place-items-center"
                aria-label="Close"
            >
                <X className="size-5" aria-hidden />
            </DialogPrimitive.Close>
        </div>
    );
}

export interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    cancelLabel?: string;
    danger?: boolean;
    onConfirm: () => void;
}

/** Ask before destructive or important actions. The button label states the action ("Delete job"), never just "OK". */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancel',
    danger,
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            footer={
                <>
                    <Button variant="secondary" onClick={() => onOpenChange(false)}>
                        {cancelLabel}
                    </Button>
                    <Button
                        variant={danger ? 'danger' : 'primary'}
                        onClick={() => {
                            onConfirm();
                            onOpenChange(false);
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        />
    );
}

/** Panel that slides up from the bottom - the mobile-friendly alternative to a dialog or menu. */
export function BottomSheet({ open, onOpenChange, trigger, title, description, children, footer }: DialogBaseProps) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            {trigger && <DialogPrimitive.Trigger asChild>{trigger}</DialogPrimitive.Trigger>}
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/50" />
                <DialogPrimitive.Content className="bg-surface-raised shadow-raised fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto rounded-t-2xl p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                    <div className="bg-line mx-auto mb-4 h-1.5 w-12 rounded-full" aria-hidden />
                    <DialogHeader title={title} description={description} />
                    {children && <div className="text-fg mt-4 text-sm">{children}</div>}
                    {footer && <div className="mt-6 flex flex-col gap-2">{footer}</div>}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
