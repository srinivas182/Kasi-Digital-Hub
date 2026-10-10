import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { LessonView, type SnapshotLesson } from '../../components/LessonView';

interface Props {
    course: {
        id: string;
        title: string;
        status: string;
        provider: string;
        minAge: number;
        accreditation: { body: string; number: string; status: string } | null;
    };
    preview: { summary: string | null; outcomes: string[]; modules: { title: string; lessons: SnapshotLesson[] }[] };
    aiDrafted: string[];
    problems: { title: string; code: string }[];
    history: { kind: string; body: string | null; at: string; by: string | null }[];
}

export default function ReviewCourse({ course, preview, aiDrafted, problems, history }: Props) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const [comment, setComment] = useState('');
    const send = (decision: string) => router.post(`/learn/review/courses/${course.id}`, { decision, comment });
    const [open, setOpen] = useState<string | null>(preview.modules[0]?.lessons[0]?.id ?? null);
    const lessons = preview.modules.flatMap((m) => m.lessons);
    const current = lessons.find((l) => l.id === open);

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={course.title} />
            <Link href="/learn/review" className="text-primary text-sm font-semibold hover:underline">
                ← {t('learn.review.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{course.title}</h1>
            <p className="text-fg-muted">
                {course.provider} · {course.minAge < 18 ? t('learn.age.16') : t('learn.age.18')}
                {course.accreditation &&
                    ` · ${course.accreditation.body} ${course.accreditation.number} (${t(`learn.accreditation.${course.accreditation.status}`)})`}
            </p>
            {errors?.course && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.course} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[16rem_1fr_20rem]">
                <nav aria-label={t('learn.author.lessons')}>
                    <ol className="flex flex-col gap-1 text-sm">
                        {lessons.map((l) => (
                            <li key={l.id}>
                                <button
                                    type="button"
                                    aria-current={open === l.id ? 'true' : undefined}
                                    className="text-primary min-h-10 text-left hover:underline aria-[current]:font-bold"
                                    onClick={() => setOpen(l.id)}
                                >
                                    {l.title}
                                </button>
                                {aiDrafted.includes(l.title) && (
                                    <Badge tone="warning">{t('learn.author.ai_drafted')}</Badge>
                                )}
                            </li>
                        ))}
                    </ol>
                </nav>
                <Card>
                    {current ? <LessonView lesson={current} /> : <p className="text-fg-muted">{preview.summary}</p>}
                </Card>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('learn.author.checks')}</CardTitle>
                        <ul className="mt-2 list-disc pl-5 text-sm">
                            {problems.map((p, i) => (
                                <li key={i}>
                                    {p.title}: {t(`learn.problem.${p.code}`)}
                                </li>
                            ))}
                            {problems.length === 0 && <li>{t('learn.author.no_problems')}</li>}
                        </ul>
                    </Card>
                    <Card>
                        <CardTitle>{t('learn.review.decision')}</CardTitle>
                        <div className="mt-3 flex flex-col gap-3">
                            <Field label={t('learn.author.comment')} error={errors?.comment}>
                                <Textarea rows={3} value={comment} onChange={(e) => setComment(e.target.value)} />
                            </Field>
                            {course.status === 'in_review' && (
                                <>
                                    <Button onClick={() => send('approve')}>{t('learn.review.approve_publish')}</Button>
                                    <Button
                                        variant="secondary"
                                        disabled={!comment.trim()}
                                        onClick={() => send('changes')}
                                    >
                                        {t('learn.author.send_back')}
                                    </Button>
                                </>
                            )}
                            {course.status === 'published' && (
                                <Button variant="danger" disabled={!comment.trim()} onClick={() => send('unpublish')}>
                                    {t('learn.review.unpublish')}
                                </Button>
                            )}
                        </div>
                    </Card>
                    <Card>
                        <CardTitle>{t('learn.author.history')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-2 text-sm">
                            {history.map((h, i) => (
                                <li key={i}>
                                    <span className="text-fg font-medium">{t(`learn.history.${h.kind}`)}</span>{' '}
                                    <span className="text-fg-muted">
                                        {h.by} · {formatDateTime(h.at)}
                                    </span>
                                    {h.body && <p className="text-fg-muted">{h.body}</p>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
