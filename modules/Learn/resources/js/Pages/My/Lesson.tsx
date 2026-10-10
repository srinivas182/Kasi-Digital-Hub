import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { LessonPlayer } from '../../components/LessonPlayer';
import type { SnapshotLesson } from '../../components/LessonView';
import { record, sync } from '../../components/offline';
import { postJson } from '../../components/postJson';
import { markPractice, type QuizQuestion, QuizView } from '../../components/QuizView';

interface Props {
    enrolment: { id: string; progress: number };
    course: { title: string };
    lesson: SnapshotLesson & {
        quiz?: { graded: boolean; pass_mark: number; max_attempts: number; questions: QuizQuestion[] } | null;
        assignment?: { instructions: string; rubric: string[]; evidence: string[]; max_resubmissions: number } | null;
    };
    state: { done: boolean; position: number; practiceScore: number | null };
    previous: string | null;
    next: string | null;
    quiz: { best: number | null; passed: boolean; attemptsLeft: number } | null;
    submissions: {
        id: string;
        status: string;
        attempt: number;
        feedback: string | null;
        rubric: string[];
        text: string | null;
        hasFile: boolean;
        at: string;
    }[];
}

export default function LessonPage({ enrolment, course, lesson, state, previous, next, quiz, submissions }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [done, setDone] = useState(state.done);
    const base = `/learn/my/${enrolment.id}`;
    const submit = useForm<{ text: string; file: File | null }>({ text: '', file: null });
    const latest = submissions[0];
    const canSubmit =
        lesson.assignment &&
        (!latest || (latest.status === 'not_yet' && submissions.length <= lesson.assignment.max_resubmissions));

    useEffect(() => {
        void sync();
    }, []);

    const finish = () => {
        setDone(true);
        void record({ enrolment: enrolment.id, lesson: lesson.id, type: 'completed' });
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={lesson.title} />
            <Link href={base} className="text-primary text-sm font-semibold hover:underline">
                ← {course.title}
            </Link>
            <div className="mt-2 max-w-3xl">
                <ProgressBar
                    value={enrolment.progress}
                    label={t('learn.my.progress', { progress: enrolment.progress })}
                />
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.submission && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.submission} />
                </div>
            )}
            <Card className="mt-4 max-w-3xl">
                <h1 className="text-fg text-xl font-bold">{lesson.title}</h1>
                <div className="mt-3">
                    {lesson.kind === 'quiz' && lesson.quiz ? (
                        <>
                            {lesson.quiz.graded ? (
                                <p className="text-fg-muted mb-3 text-sm">
                                    {quiz?.passed
                                        ? t('learn.quiz.already_passed')
                                        : t('learn.quiz.graded_hint', {
                                              mark: lesson.quiz.pass_mark,
                                              left: quiz?.attemptsLeft ?? lesson.quiz.max_attempts,
                                          })}
                                </p>
                            ) : (
                                <p className="text-fg-muted mb-3 text-sm">{t('learn.quiz.practice_hint')}</p>
                            )}
                            <QuizView
                                questions={lesson.quiz.questions}
                                graded={lesson.quiz.graded}
                                passMark={lesson.quiz.pass_mark}
                                disabled={lesson.quiz.graded && (quiz?.passed || quiz?.attemptsLeft === 0)}
                                onSubmit={async (answers) => {
                                    if (!lesson.quiz?.graded) {
                                        const result = markPractice(lesson.quiz?.questions ?? [], answers);
                                        void record({
                                            enrolment: enrolment.id,
                                            lesson: lesson.id,
                                            type: 'practice',
                                            value: result.score,
                                        });
                                        return result;
                                    }
                                    const result = await postJson<{
                                        ok: boolean;
                                        score: number;
                                        passed: boolean;
                                        results: { id: number; correct: boolean; explanation: string | null }[];
                                        message?: string;
                                    }>(`${base}/quiz/${lesson.id}`, { answers }).catch(() => ({
                                        ok: false,
                                        score: 0,
                                        passed: false,
                                        results: [],
                                        message: t('learn.quiz.offline'),
                                    }));
                                    if (result.ok && result.passed)
                                        router.reload({ only: ['enrolment', 'quiz', 'state'] });
                                    return result.ok
                                        ? result
                                        : { score: 0, message: result.message ?? t('learn.quiz.offline') };
                                }}
                            />
                        </>
                    ) : lesson.kind === 'assignment' && lesson.assignment ? (
                        <div className="flex flex-col gap-4">
                            <p className="text-fg whitespace-pre-line">{lesson.assignment.instructions}</p>
                            {lesson.assignment.rubric.length > 0 && (
                                <div>
                                    <p className="text-fg text-sm font-semibold">{t('learn.assignment.criteria')}</p>
                                    <ul className="text-fg list-disc pl-5 text-sm">
                                        {lesson.assignment.rubric.map((r) => (
                                            <li key={r}>{r}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {submissions.map((s) => (
                                <Card key={s.id} className="bg-surface-muted">
                                    <CardTitle>
                                        {t('learn.assignment.attempt', { number: s.attempt })}{' '}
                                        <Badge
                                            tone={
                                                s.status === 'competent'
                                                    ? 'success'
                                                    : s.status === 'not_yet'
                                                      ? 'danger'
                                                      : 'warning'
                                            }
                                        >
                                            {t(`learn.assignment.status.${s.status}`)}
                                        </Badge>
                                    </CardTitle>
                                    <p className="text-fg-muted text-xs">{formatDateTime(s.at)}</p>
                                    {s.feedback && (
                                        <p className="text-fg mt-2 text-sm whitespace-pre-line">{s.feedback}</p>
                                    )}
                                </Card>
                            ))}
                            {canSubmit && (
                                <form
                                    className="flex flex-col gap-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        submit.post(`${base}/assignment/${lesson.id}`, {
                                            forceFormData: true,
                                            preserveScroll: true,
                                            onSuccess: () => submit.reset(),
                                        });
                                    }}
                                >
                                    {lesson.assignment.evidence.includes('text') && (
                                        <Field label={t('learn.assignment.answer')} error={submit.errors.text}>
                                            <Textarea
                                                rows={6}
                                                value={submit.data.text}
                                                onChange={(e) => submit.setData('text', e.target.value)}
                                            />
                                        </Field>
                                    )}
                                    {(lesson.assignment.evidence.includes('photo') ||
                                        lesson.assignment.evidence.includes('pdf')) && (
                                        <Field label={t('learn.assignment.file')} error={submit.errors.file}>
                                            <FileInput
                                                file={submit.data.file}
                                                onChange={(file) => submit.setData('file', file)}
                                                chooseLabel={t('documents.file')}
                                                photoLabel={t('documents.take_photo')}
                                            />
                                        </Field>
                                    )}
                                    <div>
                                        <Button type="submit" loading={submit.processing}>
                                            {t('learn.assignment.submit')}
                                        </Button>
                                    </div>
                                    <p className="text-fg-muted text-xs">{t('learn.assignment.online_only')}</p>
                                </form>
                            )}
                        </div>
                    ) : (
                        <LessonPlayer
                            lesson={lesson}
                            done={done}
                            position={state.position}
                            onFinished={finish}
                            onPosition={(s) =>
                                void record({ enrolment: enrolment.id, lesson: lesson.id, type: 'position', value: s })
                            }
                        />
                    )}
                </div>
            </Card>
            <nav aria-label={t('learn.player.nav')} className="mt-4 flex max-w-3xl justify-between gap-2">
                {previous ? (
                    <Link
                        href={`${base}/lessons/${previous}`}
                        className="text-primary min-h-11 font-semibold hover:underline"
                    >
                        ← {t('learn.player.previous')}
                    </Link>
                ) : (
                    <span />
                )}
                {next ? (
                    <Link
                        href={`${base}/lessons/${next}`}
                        className="text-primary min-h-11 font-semibold hover:underline"
                    >
                        {t('learn.player.next')} →
                    </Link>
                ) : (
                    <Link href={base} className="text-primary min-h-11 font-semibold hover:underline">
                        {t('learn.player.back_to_course')}
                    </Link>
                )}
            </nav>
        </AppLayout>
    );
}
