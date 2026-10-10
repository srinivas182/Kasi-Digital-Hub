import { Link } from '@inertiajs/react';

import { Badge } from '@/components/ui/display';
import { useTranslation } from '@/lib/i18n';

import { formatBytes } from './format';

export interface OutlineModule {
    title: string;
    lessons: { id: string; title: string; kind: string; minutes: number | null; bytes: number; preview: boolean }[];
}

/** Modules and lessons with time and data per lesson; preview lessons link to the preview. */
export function Outline({ modules, slug }: { modules: OutlineModule[]; slug?: string }) {
    const { t } = useTranslation();
    return (
        <ol className="flex flex-col gap-4">
            {modules.map((m, i) => (
                <li key={i}>
                    <h3 className="text-fg font-semibold">{m.title}</h3>
                    <ol className="mt-1 flex flex-col gap-1">
                        {m.lessons.map((l) => (
                            <li
                                key={l.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b py-1 text-sm"
                            >
                                <span className="text-fg">
                                    {slug && l.preview ? (
                                        <Link
                                            href={`/learn/courses/${slug}/preview/${l.id}`}
                                            className="text-primary font-medium hover:underline"
                                        >
                                            {l.title}
                                        </Link>
                                    ) : (
                                        l.title
                                    )}{' '}
                                    {l.preview && <Badge tone="primary">{t('learn.author.preview')}</Badge>}
                                </span>
                                <span className="text-fg-muted">
                                    {t(`learn.kind.${l.kind}`)}
                                    {l.minutes ? ` · ${l.minutes} min` : ''} · {formatBytes(l.bytes)}
                                </span>
                            </li>
                        ))}
                    </ol>
                </li>
            ))}
        </ol>
    );
}
