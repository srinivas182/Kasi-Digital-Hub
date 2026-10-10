import { Head } from '@inertiajs/react';
import { CheckCircle2, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { useTranslation } from '@/lib/i18n';

import { LessonPlayer } from '../../components/LessonPlayer';
import { downloadedIndex, loadCourse, type OfflineCourse, pending, queue, sync } from '../../components/offline';
import { markPractice, QuizView } from '../../components/QuizView';

/**
 * Offline reader: works without a connection. Everything comes from the phone (downloaded courses);
 * progress is queued and sent when the phone is online again.
 */
export default function OfflineReader() {
    const { t } = useTranslation();
    const [index, setIndex] = useState<Record<string, { title: string; bytes: number; savedAt: string }>>({});
    const [course, setCourse] = useState<OfflineCourse | null>(null);
    const [lessonId, setLessonId] = useState<string | null>(null);
    const [doneIds, setDoneIds] = useState<string[]>([]);
    const [online, setOnline] = useState(typeof navigator === 'undefined' ? true : navigator.onLine);
    const [waiting, setWaiting] = useState(() => (typeof window === 'undefined' ? 0 : pending()));

    useEffect(() => {
        void downloadedIndex().then(setIndex);
        const update = () => {
            setOnline(navigator.onLine);
            void sync().then(() => setWaiting(pending()));
        };
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);

    const lessons = course?.modules.flatMap((m) => m.lessons) ?? [];
    const lesson = lessons.find((l) => l.id === lessonId);
    const mark = (type: 'completed' | 'practice', value = 0) => {
        if (!course || !lesson) return;
        queue({ enrolment: course.enrolment, lesson: lesson.id, type, value });
        setDoneIds((ids) => [...ids, lesson.id]);
        setWaiting(pending());
        void sync().then(() => setWaiting(pending()));
    };

    return (
        <main id="main" className="bg-canvas min-h-screen px-4 py-6">
            <Head title={t('learn.offline.reader')} />
            <div className="mx-auto max-w-3xl">
                <h1 className="text-fg text-2xl font-bold">{t('learn.offline.reader')}</h1>
                <p className="text-fg-muted mt-1 flex items-center gap-2 text-sm" role="status">
                    {!online && <WifiOff className="size-4" aria-hidden />}
                    {online ? t('learn.offline.online') : t('learn.offline.offline')}
                    {waiting > 0 && ` · ${t('learn.offline.waiting', { count: waiting })}`}
                </p>
                {!course && (
                    <ul className="mt-4 flex flex-col gap-2">
                        {Object.entries(index).map(([id, c]) => (
                            <li key={id}>
                                <Button
                                    variant="secondary"
                                    className="w-full justify-start"
                                    onClick={() => void loadCourse(id).then(setCourse)}
                                >
                                    {c.title}
                                </Button>
                            </li>
                        ))}
                        {Object.keys(index).length === 0 && <Alert tone="info" title={t('learn.offline.none')} />}
                    </ul>
                )}
                {course && !lesson && (
                    <Card className="mt-4">
                        <Button size="sm" variant="ghost" onClick={() => setCourse(null)}>
                            ← {t('learn.offline.all')}
                        </Button>
                        <h2 className="text-fg mt-2 text-xl font-bold">{course.title}</h2>
                        {course.modules.map((m, i) => (
                            <section key={i} className="mt-3">
                                <h3 className="text-fg font-semibold">{m.title}</h3>
                                <ul className="mt-1 flex flex-col gap-1">
                                    {m.lessons.map((l) => (
                                        <li key={l.id}>
                                            <button
                                                type="button"
                                                className="text-primary flex min-h-10 items-center gap-2 text-left hover:underline"
                                                onClick={() => setLessonId(l.id)}
                                            >
                                                {doneIds.includes(l.id) && (
                                                    <CheckCircle2
                                                        className="text-success-text size-4"
                                                        aria-label={t('learn.player.finished')}
                                                    />
                                                )}
                                                {l.title}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </Card>
                )}
                {course && lesson && (
                    <Card className="mt-4">
                        <Button size="sm" variant="ghost" onClick={() => setLessonId(null)}>
                            ← {course.title}
                        </Button>
                        <h2 className="text-fg mt-2 text-xl font-bold">{lesson.title}</h2>
                        <div className="mt-3">
                            {lesson.kind === 'quiz' && lesson.quiz ? (
                                lesson.quiz.graded ? (
                                    <Alert tone="info" title={t('learn.offline.graded_online')} />
                                ) : (
                                    <QuizView
                                        questions={lesson.quiz.questions}
                                        graded={false}
                                        onSubmit={async (answers) => {
                                            const result = markPractice(lesson.quiz?.questions ?? [], answers);
                                            mark('practice', result.score);
                                            return result;
                                        }}
                                    />
                                )
                            ) : lesson.kind === 'assignment' ? (
                                <Alert tone="info" title={t('learn.offline.assignment_online')} />
                            ) : (
                                <LessonPlayer
                                    lesson={lesson}
                                    done={doneIds.includes(lesson.id)}
                                    position={0}
                                    onFinished={() => mark('completed')}
                                    onPosition={() => undefined}
                                />
                            )}
                        </div>
                    </Card>
                )}
            </div>
        </main>
    );
}
