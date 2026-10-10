import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Checkbox } from '@/components/ui/Checkbox';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    submission: {
        id: string;
        status: string;
        attempt: number;
        text: string | null;
        fileUrl: string | null;
        feedback: string | null;
        rubric: string[];
        at: string;
    };
    learner: { name: string };
    course: string;
    lesson: { title: string; instructions: string; rubric: string[] };
}

export default function AssessShow({ submission, learner, course, lesson }: Props) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const form = useForm({ criteria: [] as string[], feedback: '' });
    const open = submission.status === 'submitted';

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.assess.title')} />
            <Link href="/learn/assess" className="text-primary text-sm font-semibold hover:underline">
                ← {t('learn.assess.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">
                {learner.name} - {lesson.title}
            </h1>
            <p className="text-fg-muted">
                {course} · {t('learn.assignment.attempt', { number: submission.attempt })} ·{' '}
                {formatDateTime(submission.at)}
            </p>
            {errors?.assess && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.assess} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('learn.assess.instructions')}</CardTitle>
                    <p className="text-fg mt-2 text-sm whitespace-pre-line">{lesson.instructions}</p>
                    <CardTitle className="mt-4">{t('learn.assess.evidence')}</CardTitle>
                    {submission.text && <p className="text-fg mt-2 whitespace-pre-line">{submission.text}</p>}
                    {submission.fileUrl && (
                        <a
                            href={submission.fileUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary mt-2 inline-block font-semibold hover:underline"
                        >
                            {t('learn.assess.open_file')}
                        </a>
                    )}
                </Card>
                <Card>
                    {open ? (
                        <form
                            className="flex flex-col gap-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                // The decision comes from the button pressed (not from state set in the same click).
                                const competent =
                                    ((e.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null)?.value ===
                                    'competent';
                                form.transform((d) => ({ ...d, competent }));
                                form.post(`/learn/assess/${submission.id}`);
                            }}
                        >
                            <fieldset>
                                <legend className="text-fg font-semibold">{t('learn.assignment.criteria')}</legend>
                                {lesson.rubric.map((c) => (
                                    <Checkbox
                                        key={c}
                                        label={c}
                                        checked={form.data.criteria.includes(c)}
                                        onCheckedChange={(v) =>
                                            form.setData(
                                                'criteria',
                                                v === true
                                                    ? [...form.data.criteria, c]
                                                    : form.data.criteria.filter((x) => x !== c),
                                            )
                                        }
                                    />
                                ))}
                            </fieldset>
                            <Field
                                label={t('learn.assess.feedback')}
                                hint={t('learn.assess.feedback_hint')}
                                error={form.errors.feedback}
                                required
                            >
                                <Textarea
                                    rows={5}
                                    value={form.data.feedback}
                                    onChange={(e) => form.setData('feedback', e.target.value)}
                                />
                            </Field>
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" name="decision" value="competent" loading={form.processing}>
                                    {t('learn.assignment.status.competent')}
                                </Button>
                                <Button
                                    type="submit"
                                    name="decision"
                                    value="not_yet"
                                    variant="secondary"
                                    loading={form.processing}
                                >
                                    {t('learn.assignment.status.not_yet')}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <>
                            <Badge tone={submission.status === 'competent' ? 'success' : 'danger'}>
                                {t(`learn.assignment.status.${submission.status}`)}
                            </Badge>
                            <p className="text-fg mt-2 whitespace-pre-line">{submission.feedback}</p>
                        </>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
