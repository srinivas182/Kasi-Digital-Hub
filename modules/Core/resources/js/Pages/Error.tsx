import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Clock, Lock, SearchX, Wrench } from 'lucide-react';

import { buttonVariants } from '@/components/ui/Button';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

const ICONS = { 403: Lock, 404: SearchX, 419: Clock, 429: Clock, 500: AlertTriangle, 503: Wrench } as const;

type Status = keyof typeof ICONS;

/** Branded, plain-language error page with a clear way back. */
export default function ErrorPage({ status }: { status: number }) {
    const { t } = useTranslation();
    const code = (status in ICONS ? status : 500) as Status;
    const Icon = ICONS[code];

    return (
        <PublicLayout>
            <Head title={t(`error.${code}.title`)} />
            <section className="mx-auto flex max-w-xl flex-col items-center px-4 py-20 text-center">
                <span className="bg-primary-soft text-primary grid size-16 place-items-center rounded-2xl">
                    <Icon className="size-8" aria-hidden />
                </span>
                <p className="text-fg-muted mt-6 text-sm font-semibold">Error {code}</p>
                <h1 className="text-fg mt-1 text-3xl font-bold tracking-tight">{t(`error.${code}.title`)}</h1>
                <p className="text-fg-muted mt-3">{t(`error.${code}.body`)}</p>
                <Link href="/" className={buttonVariants({ size: 'lg', className: 'mt-8' })}>
                    {t('common.back_home')}
                </Link>
            </section>
        </PublicLayout>
    );
}
