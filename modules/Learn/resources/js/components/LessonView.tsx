import { useTranslation } from '@/lib/i18n';

import { formatBytes } from './format';

export interface SnapshotLesson {
    id: string;
    title: string;
    kind: string;
    minutes: number | null;
    preview: boolean;
    html: string;
    transcript: string | null;
    bytes: number;
    media: { id: string; kind: string; duration: number | null; name: string; versions: Record<string, number> } | null;
}

/**
 * A published lesson. The HTML comes from the server's allow-list renderer (ContentRenderer), so it
 * contains only safe elements. Video shows the size of each version before it plays.
 */
export function LessonView({ lesson }: { lesson: SnapshotLesson }) {
    const { t } = useTranslation();
    const media = lesson.media;

    return (
        <article>
            <h2 className="text-fg text-xl font-bold">{lesson.title}</h2>
            {media && media.kind === 'video' && (
                <div className="mt-3">
                    <video
                        controls
                        preload="none"
                        className="w-full rounded-lg"
                        poster={`/learn/media/${media.id}/thumb`}
                    >
                        <source src={`/learn/media/${media.id}/low`} type="video/mp4" />
                    </video>
                    <p className="text-fg-muted mt-1 text-xs">
                        {Object.entries(media.versions)
                            .map(([name, bytes]) => `${t(`learn.media.version.${name}`)}: ${formatBytes(bytes)}`)
                            .join(' · ')}
                    </p>
                </div>
            )}
            {media && media.kind === 'audio' && (
                <audio controls preload="none" className="mt-3 w-full">
                    <source src={`/learn/media/${media.id}/audio`} type="audio/mp4" />
                </audio>
            )}
            {media && media.kind === 'download' && (
                <a
                    href={`/learn/media/${media.id}`}
                    className="text-primary mt-3 inline-block font-semibold hover:underline"
                >
                    {t('learn.lesson.download', { name: media.name })}
                </a>
            )}
            {/* Server-rendered from an allow-list (ContentRenderer): only safe elements. */}
            <div className="lesson-content mt-4" dangerouslySetInnerHTML={{ __html: lesson.html }} />
            {lesson.transcript && (
                <details className="mt-4">
                    <summary className="text-primary cursor-pointer font-semibold">
                        {t('learn.lesson.transcript')}
                    </summary>
                    <p className="text-fg mt-2 whitespace-pre-line">{lesson.transcript}</p>
                </details>
            )}
        </article>
    );
}
