import { Head } from '@inertiajs/react';
import { HeartHandshake } from 'lucide-react';

import { Card, CardTitle } from '@/components/ui/display';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { PageHero } from '../components/PageHero';

export default function About() {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <Head title={t('site.about.title')} />
            <PageHero eyebrow={t('site.about.title')} title={t('site.about.headline')} />
            <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[2fr_1fr]">
                <div className="text-fg flex flex-col gap-4 text-lg leading-8">
                    {[1, 2, 3].map((p) => (
                        <p key={p}>{t(`site.about.body.${p}`)}</p>
                    ))}
                </div>
                <Card>
                    <CardTitle>{t('site.about.values_title')}</CardTitle>
                    <ul className="mt-4 flex flex-col gap-3">
                        {[1, 2, 3].map((v) => (
                            <li key={v} className="text-fg flex gap-2 text-sm">
                                <HeartHandshake className="text-primary size-5 shrink-0" aria-hidden />
                                {t(`site.about.values.${v}`)}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </PublicLayout>
    );
}
