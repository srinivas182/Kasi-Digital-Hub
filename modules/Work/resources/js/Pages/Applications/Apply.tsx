import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { RadioGroup } from '@/components/ui/RadioGroup';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    person: { name: string; assisted: boolean };
    job: { id: string; title: string; employer: string };
    questions: { question: string; kind: string }[];
    cvs: { id: string; template: string; createdAt: string }[];
    hasProfile: boolean;
    gaps: string[];
}

export default function Apply({ person, job, questions, cvs, hasProfile, gaps }: Props) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const form = useForm({ cv_id: cvs[0]?.id ?? '', answers: questions.map(() => ''), message: '', share: false });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.apply.title', { title: job.title })} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <div className="mx-auto max-w-2xl">
                <h1 className="text-fg mt-2 text-2xl font-bold">{t('work.apply.title', { title: job.title })}</h1>
                <p className="text-fg-muted">{job.employer}</p>
                {errors?.apply && (
                    <div className="mt-4">
                        <Alert tone="danger" title={errors.apply} />
                    </div>
                )}
                {!hasProfile ? (
                    <Card className="mt-6">
                        <p className="text-fg">{t('work.apply.no_profile')}</p>
                        <Link href="/work/profile" className={buttonVariants({ className: 'mt-3' })}>
                            {t('work.cv.go_profile')}
                        </Link>
                    </Card>
                ) : (
                    <Card className="mt-6">
                        {gaps.length > 0 && (
                            <Alert tone="warning" title={t('work.apply.gaps')}>
                                <ul className="list-disc pl-5">
                                    {gaps.map((g) => (
                                        <li key={g}>{g}</li>
                                    ))}
                                </ul>
                            </Alert>
                        )}
                        <form
                            className="mt-4 flex flex-col gap-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.transform((d) => ({ ...d, cv_id: d.cv_id === 'new' ? null : d.cv_id || null }));
                                form.post(`/work/jobs/${job.id}/apply`);
                            }}
                        >
                            <RadioGroup
                                legend={t('work.apply.cv')}
                                value={form.data.cv_id || 'new'}
                                onValueChange={(v) => form.setData('cv_id', v)}
                                options={[
                                    ...cvs.map((cv) => ({
                                        value: cv.id,
                                        label: `${t(`work.cv.template.${cv.template}`)} · ${formatDateTime(cv.createdAt)}`,
                                    })),
                                    { value: 'new', label: t('work.apply.new_cv') },
                                ]}
                            />
                            {questions.length > 0 && (
                                <fieldset className="flex flex-col gap-4">
                                    <legend className="text-fg font-semibold">{t('work.apply.questions')}</legend>
                                    {questions.map((q, i) =>
                                        q.kind === 'yes_no' ? (
                                            <RadioGroup
                                                key={i}
                                                legend={q.question}
                                                value={form.data.answers[i] ?? ''}
                                                onValueChange={(v) =>
                                                    form.setData(
                                                        'answers',
                                                        form.data.answers.map((a, j) => (j === i ? v : a)),
                                                    )
                                                }
                                                options={[
                                                    { value: t('work.apply.yes'), label: t('work.apply.yes') },
                                                    { value: t('work.apply.no'), label: t('work.apply.no') },
                                                ]}
                                            />
                                        ) : (
                                            <Field key={i} label={q.question}>
                                                <Input
                                                    value={form.data.answers[i] ?? ''}
                                                    maxLength={300}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'answers',
                                                            form.data.answers.map((a, j) =>
                                                                j === i ? e.target.value : a,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </Field>
                                        ),
                                    )}
                                </fieldset>
                            )}
                            <Field label={t('work.apply.message')} error={form.errors.message}>
                                <Textarea
                                    rows={3}
                                    maxLength={500}
                                    value={form.data.message}
                                    onChange={(e) => form.setData('message', e.target.value)}
                                />
                            </Field>
                            <Checkbox
                                label={t('work.apply.share', { employer: job.employer })}
                                checked={form.data.share}
                                onCheckedChange={(c) => form.setData('share', c === true)}
                            />
                            {form.errors.share && <p className="text-danger-text text-sm">{form.errors.share}</p>}
                            <div>
                                <Button type="submit" loading={form.processing} disabled={!form.data.share}>
                                    {t('work.apply.submit')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
