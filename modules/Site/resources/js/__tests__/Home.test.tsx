import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import Home from '../Pages/Home';

vi.mock('@inertiajs/react', async () => {
    const { sharedProps: props } = await import('@/__tests__/inertia-mock');
    return {
        Head: () => null,
        Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
        usePage: () => ({ props, url: '/' }),
        router: { post: vi.fn() },
    };
});

describe('Site/Home', () => {
    it('shows the sign-up call to action, impact numbers and hubs', () => {
        render(
            <Home
                impact={{ people: 2418, hubs: 11, organisations: 9, verifiedDocuments: 6 }}
                hubs={[
                    {
                        slug: 'tsutsumani',
                        name: 'Tsutsumani Digital Hub',
                        place: 'Tsutsumani',
                        city: 'Greater Giyani',
                        province: 'Limpopo',
                    },
                ]}
                hubCount={12}
            />,
        );

        expect(screen.getAllByRole('link', { name: 'site.home.cta' })[0]).toHaveAttribute('href', '/login');
        expect(screen.getByText('2 418')).toBeInTheDocument(); // en-ZA number grouping
        expect(screen.getByRole('link', { name: /Tsutsumani Digital Hub/ })).toHaveAttribute(
            'href',
            '/hubs/tsutsumani',
        );
        expect(screen.getByText(/Demo environment/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute('href', '#main');
    });
});
