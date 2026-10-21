import { Head } from '@inertiajs/react';

import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { SummaryCard, type Summary } from '../components/SummaryCard';

/** A business summary someone chose to share with you (S29). Bands only; this view is logged. */
export default function Shared({ summary }: { summary: Summary }) {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <Head title={t('biz.shared.title', { business: summary.business })} />
            <div className="mx-auto max-w-xl px-4 py-8">
                <h1 className="text-fg text-2xl font-bold">{t('biz.shared.title', { business: summary.business })}</h1>
                <p className="text-fg-muted mt-1 text-sm">{t('biz.shared.lead')}</p>
                <SummaryCard summary={summary} />
            </div>
        </PublicLayout>
    );
}
