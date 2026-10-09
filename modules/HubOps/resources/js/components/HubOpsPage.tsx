import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Select } from '@/components/ui/form';
import type { Crumb } from '@/components/ui/navigation';
import { ConsoleLayout } from '@/layouts/ConsoleLayout';
import { useTranslation } from '@/lib/i18n';

export interface HubChoice {
    current: { id: string; name: string };
    options: { id: string; name: string }[];
}

interface HubOpsPageProps {
    title: string;
    hubs?: HubChoice;
    crumbs?: Crumb[];
    actions?: ReactNode;
    children: ReactNode;
}

/** KasiHub Ops page frame: title, hub selector (when there is a choice), flash and help banner. */
export function HubOpsPage({ title, hubs, crumbs = [], actions, children }: HubOpsPageProps) {
    const { t } = useTranslation();
    const { flash, auth, assist, errors } = usePage().props;

    return (
        <ConsoleLayout
            module="HubOps"
            breadcrumbs={[{ label: 'KasiHub Ops', href: '/hub-ops' }, ...crumbs]}
            userName={auth.user?.name}
        >
            <Head title={title} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold tracking-tight">{title}</h1>
                    {hubs && hubs.options.length <= 1 && <p className="text-fg-muted text-sm">{hubs.current.name}</p>}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {hubs && hubs.options.length > 1 && (
                        <Select
                            aria-label={t('hubops.hub')}
                            className="max-w-64"
                            value={hubs.current.id}
                            onChange={(e) => router.get(window.location.pathname, { hub: e.target.value })}
                        >
                            {hubs.options.map((h) => (
                                <option key={h.id} value={h.id}>
                                    {h.name}
                                </option>
                            ))}
                        </Select>
                    )}
                    {actions}
                </div>
            </div>
            {assist && (
                <div className="mt-4">
                    <Alert tone="warning" title={t('hubops.assist.banner', { name: assist.name })} />
                </div>
            )}
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.assist && (
                <div className="mt-4">
                    <Alert tone="danger" title={errors.assist} />
                </div>
            )}
            <div className="mt-6">{children}</div>
        </ConsoleLayout>
    );
}

export interface Paginated<T> {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
}

export function purposeLabel(t: (key: string) => string, purpose: string) {
    return t(`hubops.purpose.${purpose}`);
}
