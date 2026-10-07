import { Head } from '@inertiajs/react';

import { PublicLayout } from '@/layouts/PublicLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export default function Legal({
    title,
    version,
    publishedAt,
    body,
}: {
    title: string;
    version: number;
    publishedAt: string;
    body: string;
}) {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <Head title={title} />
            <article className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <h1 className="text-fg text-3xl font-bold tracking-tight">{title}</h1>
                <p className="text-fg-muted mt-2 text-sm">
                    {t('legal.version', { version, date: formatDate(publishedAt) })}
                </p>
                <div className="text-fg mt-8 space-y-4 leading-7">
                    {body.split(/\n{2,}/).map((paragraph, index) => (
                        <p key={index}>{paragraph}</p>
                    ))}
                </div>
            </article>
        </PublicLayout>
    );
}
