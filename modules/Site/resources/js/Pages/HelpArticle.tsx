import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { PublicLayout } from '@/layouts/PublicLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    article: { slug: string; title: string; category: string; html: string; lastChecked: string | null };
    related: { slug: string; title: string }[];
}

/** A help article. The HTML is built on the server from the safe help format (everything escaped). */
export default function HelpArticle({ article, related }: Props) {
    const { t } = useTranslation();
    const [answered, setAnswered] = useState(false);
    const send = async (helpful: boolean) => {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        await fetch(`/help/${article.slug}/feedback`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': match?.[1] ? decodeURIComponent(match[1]) : '',
            },
            body: JSON.stringify({ helpful }),
        }).catch(() => null);
        setAnswered(true);
    };
    return (
        <PublicLayout>
            <Head title={article.title} />
            <article className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                <Link href="/help" className="text-primary text-sm font-semibold hover:underline">
                    ← {t('site.help.title')}
                </Link>
                <h1 className="text-fg mt-2 text-3xl font-bold">{article.title}</h1>
                {article.lastChecked && (
                    <p className="text-fg-muted mt-1 text-sm">
                        {t('help.last_checked', { date: formatDate(article.lastChecked) })}
                    </p>
                )}
                <div
                    className="help-content text-fg mt-6 flex flex-col gap-4 [&_h2]:text-xl [&_h2]:font-bold [&_ul]:list-disc [&_ul]:pl-5"
                    dangerouslySetInnerHTML={{ __html: article.html }}
                />
                <div className="border-line mt-8 border-t pt-4">
                    {answered ? (
                        <p className="text-fg" role="status">
                            {t('help.thanks')}
                        </p>
                    ) : (
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-fg">{t('help.helpful')}</span>
                            <Button size="sm" variant="secondary" onClick={() => void send(true)}>
                                {t('common.yes')}
                            </Button>
                            <Button size="sm" variant="secondary" onClick={() => void send(false)}>
                                {t('common.no')}
                            </Button>
                        </div>
                    )}
                    <p className="mt-4 text-sm">
                        <Link href="/help/requests" className="text-primary font-semibold hover:underline">
                            {t('support.still_need_help')}
                        </Link>
                    </p>
                </div>
                {related.length > 0 && (
                    <nav className="mt-8" aria-label={t('help.related')}>
                        <h2 className="text-fg font-bold">{t('help.related')}</h2>
                        <ul className="mt-2 flex flex-col gap-1">
                            {related.map((r) => (
                                <li key={r.slug}>
                                    <Link href={`/help/${r.slug}`} className="text-primary hover:underline">
                                        {r.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
            </article>
        </PublicLayout>
    );
}
