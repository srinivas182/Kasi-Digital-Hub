import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import { ConfirmDialog } from '../Dialog';

describe('ConfirmDialog', () => {
    it('runs the action and closes when confirmed', () => {
        const onConfirm = vi.fn();
        const onOpenChange = vi.fn();
        render(
            <ConfirmDialog
                open
                onOpenChange={onOpenChange}
                title="Delete this job?"
                description="This can't be undone."
                confirmLabel="Delete job"
                danger
                onConfirm={onConfirm}
            />,
        );

        expect(screen.getByRole('dialog', { name: 'Delete this job?' })).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Delete job' }));
        expect(onConfirm).toHaveBeenCalledOnce();
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('does nothing when cancelled', () => {
        const onConfirm = vi.fn();
        const onOpenChange = vi.fn();
        render(
            <ConfirmDialog
                open
                onOpenChange={onOpenChange}
                title="Delete?"
                description="Sure?"
                confirmLabel="Delete"
                onConfirm={onConfirm}
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(onConfirm).not.toHaveBeenCalled();
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });
});
