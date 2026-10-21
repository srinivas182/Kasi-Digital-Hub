import { Head, Link, usePage } from '@inertiajs/react';

import { Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

/** My designs (S30). */
export default function Designs({
    designs,
}: {
    designs: { id: number; template: string; title: string; updated_at: string }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('brand.designs.title')} />
            <Link href="/brand" className="text-primary text-sm font-semibold hover:underline">
                ← {t('brand.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('brand.designs.title')}</h1>
            <Card className="mt-4">
                <ul className="flex flex-col gap-2">
                    {designs.map((d) => (
                        <li
                            key={d.id}
                            className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                        >
                            <Link
                                href={`/brand/make/${d.template}/${d.id}`}
                                className="text-primary font-semibold hover:underline"
                            >
                                {d.title}
                            </Link>
                            <span className="text-fg-muted text-sm">
                                {t(`brand.template.${d.template}`)} · {formatDateTime(d.updated_at)}
                            </span>
                        </li>
                    ))}
                    {designs.length === 0 && <li className="text-fg-muted">{t('brand.none_yet')}</li>}
                </ul>
            </Card>
        </AppLayout>
    );
}
