import type { ReactNode } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { LanguageSwitcher } from '@/components/platform/chrome';

import { Shell } from './Shell';

/** Sign-in and sign-up steps: one centred card, nothing to distract. */
export function AuthLayout({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <Shell>
            <main id="main" className="flex min-h-screen flex-col items-center px-4 py-8 sm:justify-center">
                <div className="mb-6 flex w-full max-w-md items-center justify-between">
                    <BrandMark />
                    <LanguageSwitcher />
                </div>
                <div className="rounded-card border-line bg-surface shadow-raised w-full max-w-md border p-6 sm:p-8">
                    <h1 className="text-fg text-2xl font-bold tracking-tight">{title}</h1>
                    {description && <p className="text-fg-muted mt-2 text-sm">{description}</p>}
                    <div className="mt-6">{children}</div>
                </div>
            </main>
        </Shell>
    );
}
