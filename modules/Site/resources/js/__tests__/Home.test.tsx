import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import Home, { type PortalSummary } from '../Pages/Home';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            platform: {
                brand: 'KasiHub',
                fullName: 'Kasi Digital Hub',
                tagline: 'Jobs, skills and enterprise',
                version: '0.0.1',
                demo: true,
                locale: 'en',
            },
        },
    }),
}));

const portals: PortalSummary[] = [
    { name: 'Work', title: 'KasiWork', description: 'Jobs and AI matching', group: 'service' },
    { name: 'Admin', title: 'National admin console', description: 'Running the platform', group: 'national' },
];

describe('Site/Home', () => {
    it('shows the brand, demo badge and portals grouped', () => {
        render(<Home portals={portals} />);

        expect(screen.getByText('KasiHub')).toBeInTheDocument();
        expect(screen.getByText('Demo environment')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Service portals' })).toBeInTheDocument();
        expect(screen.getByText('KasiWork')).toBeInTheDocument();
        expect(screen.getByText('National admin console')).toBeInTheDocument();
    });
});
