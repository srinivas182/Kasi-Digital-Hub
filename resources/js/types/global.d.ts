/// <reference types="vite/client" />

import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface PlatformProps {
    brand: string;
    fullName: string;
    tagline: string;
    owner: string;
    version: string;
    demo: boolean;
}

export interface LanguageOption {
    code: string;
    name: string;
    draft: boolean;
}

export interface I18nProps {
    locale: string;
    strings: Record<string, string>;
    languages: LanguageOption[];
}

export interface NavItem {
    label: string;
    href: string;
    icon: string | null;
}

export interface NavPortal {
    module: string;
    title: string;
    group: 'front' | 'service' | 'operations' | 'national';
    icon: string | null;
    href: string | null;
    items: NavItem[];
}

export interface SharedProps extends InertiaPageProps {
    platform: PlatformProps;
    i18n: I18nProps;
    navigation: { portals: NavPortal[] };
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
}
