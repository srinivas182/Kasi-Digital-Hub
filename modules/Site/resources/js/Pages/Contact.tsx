import { Head } from '@inertiajs/react';

import { Card } from '@/components/ui/display';
import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { EnquiryForm } from '../components/EnquiryForm';
import { PageHero } from '../components/PageHero';

export default function Contact({ topics, hubs }: { topics: string[]; hubs: { slug: string; name: string }[] }) {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <Head title={t('site.contact.title')} />
            <PageHero title={t('site.contact.title')} lead={t('site.contact.lead')} />
            <div className="mx-auto max-w-2xl px-4 py-12 sm:px-6">
                <Card>
                    <EnquiryForm kind="contact" topics={topics} hubs={hubs} />
                </Card>
            </div>
        </PublicLayout>
    );
}
