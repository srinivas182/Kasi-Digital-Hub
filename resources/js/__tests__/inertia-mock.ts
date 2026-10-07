import type { SharedProps } from '@/types/global';

/** Shared props used by component tests (mirrors HandleInertiaRequests). */
export const sharedProps: Pick<SharedProps, 'platform' | 'i18n' | 'navigation'> = {
    platform: {
        brand: 'KasiHub',
        fullName: 'Kasi Digital Hub',
        tagline: 'Jobs, skills and enterprise',
        owner: 'Ku Tirhisana Consultancy (Pty) Ltd',
        version: '0.1.0',
        demo: true,
    },
    i18n: {
        locale: 'en',
        strings: {
            'common.demo_banner': 'Demo environment - all names and figures are sample data',
            'common.skip_to_content': 'Skip to main content',
            'common.sign_in': 'Sign in',
            'common.footer_owner': 'A Kasi Digital Hubs Initiative by :owner',
        },
        languages: [
            { code: 'en', name: 'English', draft: false },
            { code: 'zu', name: 'isiZulu', draft: true },
        ],
    },
    navigation: {
        portals: [
            {
                module: 'Work',
                title: 'KasiWork',
                group: 'service',
                icon: 'briefcase',
                href: '/work',
                items: [{ label: 'nav.work.matches', href: '/work', icon: 'briefcase' }],
            },
        ],
    },
};
