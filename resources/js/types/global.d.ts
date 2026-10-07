/// <reference types="vite/client" />

import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface PlatformProps {
    brand: string;
    fullName: string;
    tagline: string;
    version: string;
    demo: boolean;
    locale: string;
}

export interface SharedProps extends InertiaPageProps {
    platform: PlatformProps;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
}
