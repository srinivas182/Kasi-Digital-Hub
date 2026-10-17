import { Head, Link } from '@inertiajs/react';

import { PublicLayout } from '@/layouts/PublicLayout';
import { useTranslation } from '@/lib/i18n';

import { PageHero } from '../components/PageHero';

/** FAQ using native <details> - works without JavaScript and with screen readers. */
export default function Help({
    questions,
    categories = [],
    articles = [],
}: {
    questions: string[];
    categories?: string[];
    articles?: { slug: string; category: string; title: string; summary: string }[];
}) {
    const { t } = useTranslation();
    return (
        <PublicLayout>
            <Head title={t('site.help.title')} />
            <PageHero title={t('site.help.title')} lead={t('site.help.lead')} />
            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                {categories
                    .filter((c) => articles.some((a) => a.category === c))
                    .map((c) => (
                        <section key={c} className="mb-8" aria-labelledby={`cat-${c}`}>
                            <h2 id={`cat-${c}`} className="text-fg text-xl font-bold">
                                {t(`help.category.${c}`)}
                            </h2>
                            <ul className="mt-2 flex flex-col gap-2">
                                {articles
                                    .filter((a) => a.category === c)
                                    .map((a) => (
                                        <li key={a.slug}>
                                            <Link
                                                href={`/help/${a.slug}`}
                                                className="text-primary font-semibold hover:underline"
                                            >
                                                {a.title}
                                            </Link>
                                            <p className="text-fg-muted text-sm">{a.summary}</p>
                                        </li>
                                    ))}
                            </ul>
                        </section>
                    ))}
                <h2 className="text-fg mb-3 text-xl font-bold">{t('help.faq')}</h2>
                <div className="flex flex-col gap-3">
                    {questions.map((q) => (
                        <details key={q} id={`q-${q}`} className="rounded-card border-line bg-surface group border p-4">
                            <summary className="text-fg cursor-pointer font-semibold">{t(`site.help.q.${q}`)}</summary>
                            <p className="text-fg-muted mt-3">{t(`site.help.a.${q}`)}</p>
                        </details>
                    ))}
                </div>
                <p className="text-fg mt-8">
                    <Link href="/contact" className="text-primary font-semibold hover:underline">
                        {t('site.contact.title')}
                    </Link>{' '}
                    ·{' '}
                    <Link href="/hubs" className="text-primary font-semibold hover:underline">
                        {t('site.nav.hubs')}
                    </Link>
                </p>
            </div>
        </PublicLayout>
    );
}
