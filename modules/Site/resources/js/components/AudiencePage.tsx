import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

import { Card, CardTitle } from '@/components/ui/display';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { EnquiryForm } from './EnquiryForm';
import { PageHero } from './PageHero';

/** Shared layout for the employer and funder/partner pages: benefits and an enquiry form. */
export function AudiencePage({ section, form }: { section: 'employers' | 'funders'; form: 'employer' | 'funder' }) {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <Head title={t(`site.${section}.title`)} />
            <PageHero
                eyebrow={t(`site.${section}.title`)}
                title={t(`site.${section}.headline`)}
                lead={t(`site.${section}.lead`)}
            />
            <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-2">
                <ul className="flex flex-col gap-4">
                    {[1, 2, 3, 4].map((point) => (
                        <li key={point} className="text-fg flex gap-3">
                            <CheckCircle2 className="text-kasi-green mt-0.5 size-5 shrink-0" aria-hidden />
                            {t(`site.${section}.points.${point}`)}
                        </li>
                    ))}
                </ul>
                <Card>
                    <CardTitle>{t(`site.${section}.form_title`)}</CardTitle>
                    <div className="mt-4">
                        <EnquiryForm kind={form} />
                    </div>
                </Card>
            </div>
        </PublicLayout>
    );
}
