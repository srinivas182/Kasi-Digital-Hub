import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import { sharedProps } from '@/__tests__/inertia-mock';

import Home, { type PortalSummary } from '../Pages/Home';

vi.mock('@inertiajs/react', async () => {
    const { sharedProps: props } = await import('@/__tests__/inertia-mock');
    return {
        Head: () => null,
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
        usePage: () => ({ props, url: '/' }),
        router: { post: vi.fn() },
    };
});

const portals: PortalSummary[] = [
    { name: 'Work', title: 'KasiWork', description: 'Jobs and AI matching', group: 'service' },
    { name: 'Admin', title: 'National admin console', description: 'Running the platform', group: 'national' },
];

describe('Site/Home', () => {
    it('shows the brand, demo banner, skip link and portals grouped', () => {
        render(<Home portals={portals} />);

        expect(screen.getByText(sharedProps.platform.brand)).toBeInTheDocument();
        expect(screen.getByText(/Demo environment/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute('href', '#main');
        expect(screen.getByRole('heading', { name: 'Service portals' })).toBeInTheDocument();
        expect(screen.getByText('KasiWork')).toBeInTheDocument();
        expect(
            screen.getByText('A Kasi Digital Hubs Initiative by Ku Tirhisana Consultancy (Pty) Ltd'),
        ).toBeInTheDocument();
    });
});
