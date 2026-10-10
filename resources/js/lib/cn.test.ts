import { describe, expect, it } from 'vitest';

import { cn } from './cn';

describe('cn', () => {
    it('lets a later class in the same group win', () => {
        expect(cn('p-5 rounded-card', 'p-3')).toBe('rounded-card p-3');
        expect(cn('mt-2', 'mt-4')).toBe('mt-4');
        expect(cn('bg-primary text-primary-fg', 'bg-danger')).toBe('text-primary-fg bg-danger');
        expect(cn('h-10 w-full', 'h-12')).toBe('w-full h-12');
        expect(cn('min-h-11', 'min-h-9')).toBe('min-h-9');
        expect(cn('rounded-lg', 'rounded-full')).toBe('rounded-full');
        expect(cn('flex', 'hidden')).toBe('hidden');
        expect(cn('font-semibold', 'font-bold')).toBe('font-bold');
        expect(cn('grid-cols-2', 'grid-cols-3')).toBe('grid-cols-3');
    });

    it('keeps text size, colour and alignment apart', () => {
        expect(cn('text-sm text-fg', 'text-lg')).toBe('text-fg text-lg');
        expect(cn('text-sm text-fg', 'text-danger-text')).toBe('text-sm text-danger-text');
        expect(cn('text-center', 'text-sm')).toBe('text-center text-sm');
    });

    it('keeps border width and colour apart', () => {
        expect(cn('border border-line', 'border-primary')).toBe('border border-primary');
        expect(cn('border-2', 'border')).toBe('border');
        expect(cn('border-b border-line')).toBe('border-b border-line');
    });

    it('treats responsive and state variants separately', () => {
        expect(cn('p-2 md:p-4', 'md:p-6')).toBe('p-2 md:p-6');
        expect(cn('hover:bg-surface-muted', 'bg-primary')).toBe('hover:bg-surface-muted bg-primary');
        expect(cn('hidden sm:inline-grid')).toBe('hidden sm:inline-grid');
    });

    it('keeps unknown classes and handles conditionals', () => {
        const hidden = Math.random() > 2;
        expect(cn('lesson-content', hidden && 'x', null, undefined, { 'aria-[current]:font-bold': true })).toBe(
            'lesson-content aria-[current]:font-bold',
        );
        expect(cn('px-2 py-1', 'px-4')).toBe('py-1 px-4');
        expect(cn('gap-x-2 gap-y-4', 'gap-x-3')).toBe('gap-y-4 gap-x-3');
    });
});
