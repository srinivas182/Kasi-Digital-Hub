import { CheckCircle2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/Button';
import { useTranslation } from '@/lib/i18n';

import { formatBytes } from './format';
import type { SnapshotLesson } from './LessonView';

const QUALITY_KEY = 'kasi.learn.quality';

/**
 * The lesson content for learners: text, or video/audio with data saver (small video by default;
 * standard or audio-only on request, with sizes). Videos count as finished at 90% watched.
 */
export function LessonPlayer({
    lesson,
    done,
    position,
    onFinished,
    onPosition,
}: {
    lesson: SnapshotLesson;
    done: boolean;
    position: number;
    onFinished: () => void;
    onPosition: (seconds: number) => void;
}) {
    const { t } = useTranslation();
    const media = lesson.media;
    const [quality, setQuality] = useState<string>(() => {
        try {
            return window.localStorage.getItem(QUALITY_KEY) ?? 'low';
        } catch {
            return 'low';
        }
    });
    const reported = useRef(done);
    const lastSaved = useRef(0);
    const versions = media?.versions ?? {};
    const available = media?.kind === 'video' ? ['low', 'standard', 'audio'].filter((v) => v in versions) : [];
    const chosen = available.includes(quality) ? quality : (available[0] ?? 'audio');

    useEffect(() => {
        reported.current = done;
    }, [done]);

    const onTime = (el: HTMLMediaElement) => {
        if (el.currentTime - lastSaved.current >= 15) {
            lastSaved.current = el.currentTime;
            onPosition(Math.floor(el.currentTime));
        }
        if (!reported.current && el.duration > 0 && el.currentTime / el.duration >= 0.9) {
            reported.current = true;
            onFinished();
        }
    };

    return (
        <div>
            {media?.kind === 'video' && (
                <div>
                    <div className="mb-2 flex flex-wrap gap-2" role="group" aria-label={t('learn.player.quality')}>
                        {available.map((v) => (
                            <Button
                                key={v}
                                size="sm"
                                variant={chosen === v ? 'primary' : 'secondary'}
                                aria-pressed={chosen === v}
                                onClick={() => {
                                    setQuality(v);
                                    try {
                                        window.localStorage.setItem(QUALITY_KEY, v);
                                    } catch {
                                        /* ignore */
                                    }
                                }}
                            >
                                {t(`learn.media.version.${v}`)} · {formatBytes(versions[v] ?? 0)}
                            </Button>
                        ))}
                    </div>
                    {chosen === 'audio' ? (
                        <audio
                            key="audio"
                            controls
                            preload="none"
                            className="w-full"
                            onTimeUpdate={(e) => onTime(e.currentTarget)}
                            onLoadedMetadata={(e) => (e.currentTarget.currentTime = position)}
                        >
                            <source src={`/learn/media/${media.id}/audio`} type="audio/mp4" />
                        </audio>
                    ) : (
                        <video
                            key={chosen}
                            controls
                            preload="none"
                            className="w-full rounded-lg"
                            poster={'thumb' in versions ? `/learn/media/${media.id}/thumb` : undefined}
                            onTimeUpdate={(e) => onTime(e.currentTarget)}
                            onLoadedMetadata={(e) => (e.currentTarget.currentTime = position)}
                        >
                            <source src={`/learn/media/${media.id}/${chosen}`} type="video/mp4" />
                        </video>
                    )}
                </div>
            )}
            {media?.kind === 'audio' && (
                <audio
                    controls
                    preload="none"
                    className="w-full"
                    onTimeUpdate={(e) => onTime(e.currentTarget)}
                    onLoadedMetadata={(e) => (e.currentTarget.currentTime = position)}
                >
                    <source src={`/learn/media/${media.id}/audio`} type="audio/mp4" />
                </audio>
            )}
            {media?.kind === 'download' && (
                <a href={`/learn/media/${media.id}`} className="text-primary font-semibold hover:underline">
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
            {['text', 'video', 'audio', 'download'].includes(lesson.kind) && (
                <div className="mt-6">
                    {done ? (
                        <p className="text-success-text flex items-center gap-2 font-semibold">
                            <CheckCircle2 className="size-5" aria-hidden /> {t('learn.player.finished')}
                        </p>
                    ) : (
                        <Button onClick={onFinished}>{t('learn.player.finish')}</Button>
                    )}
                </div>
            )}
        </div>
    );
}
