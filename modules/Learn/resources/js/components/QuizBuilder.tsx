import { router } from '@inertiajs/react';
import { Plus, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

import { postJson } from './postJson';

export interface BuilderQuestion {
    kind: string;
    prompt: string;
    options: { text: string; correct: boolean; feedback?: string | null }[];
    explanation: string | null;
    aiDrafted?: boolean;
}

export interface BuilderQuiz {
    graded: boolean;
    passMark: number;
    maxAttempts: number;
    shuffle: boolean;
    questions: BuilderQuestion[];
}

const blank = (): BuilderQuestion => ({
    kind: 'single',
    prompt: '',
    options: [
        { text: '', correct: true },
        { text: '', correct: false },
    ],
    explanation: '',
});

/** Build a quiz: settings, questions, answers (mark the right ones), explanations, AI suggestions. */
export function QuizBuilder({
    quiz,
    action,
    suggestUrl,
    ai,
}: {
    quiz: BuilderQuiz;
    action: string;
    suggestUrl: string;
    ai: boolean;
}) {
    const { t } = useTranslation();
    const [data, setData] = useState<BuilderQuiz>(quiz);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const setQuestion = (i: number, q: Partial<BuilderQuestion>) =>
        setData({ ...data, questions: data.questions.map((x, j) => (j === i ? { ...x, ...q } : x)) });

    const save = () =>
        router.put(
            action,
            {
                graded: data.graded,
                pass_mark: data.passMark,
                max_attempts: data.maxAttempts,
                shuffle: data.shuffle,
                questions: data.questions.map((q) => ({
                    kind: q.kind,
                    prompt: q.prompt,
                    options: q.options,
                    explanation: q.explanation || null,
                    ai_drafted: q.aiDrafted ?? false,
                })),
            },
            { preserveScroll: true },
        );

    const suggest = async () => {
        setBusy(true);
        setMessage(null);
        const result = await postJson<{ ok: boolean; questions?: BuilderQuestion[]; message?: string }>(
            suggestUrl,
            {},
        ).catch(() => ({ ok: false, message: t('ai.fallback.unavailable') }));
        setBusy(false);
        if (result.ok && 'questions' in result && result.questions)
            setData({
                ...data,
                questions: [...data.questions, ...result.questions.map((q) => ({ ...q, aiDrafted: true }))],
            });
        else setMessage(result.message ?? t('ai.fallback.unavailable'));
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <Field label={t('learn.quiz.kind')}>
                    <Select
                        value={data.graded ? 'graded' : 'practice'}
                        onChange={(e) => setData({ ...data, graded: e.target.value === 'graded' })}
                    >
                        <option value="graded">{t('learn.quiz.graded')}</option>
                        <option value="practice">{t('learn.quiz.practice')}</option>
                    </Select>
                </Field>
                <Field label={t('learn.quiz.pass_mark')}>
                    <Input
                        type="number"
                        min={1}
                        max={100}
                        value={data.passMark}
                        onChange={(e) => setData({ ...data, passMark: Number(e.target.value) })}
                    />
                </Field>
                <Field label={t('learn.quiz.attempts')}>
                    <Input
                        type="number"
                        min={1}
                        max={10}
                        value={data.maxAttempts}
                        onChange={(e) => setData({ ...data, maxAttempts: Number(e.target.value) })}
                    />
                </Field>
            </div>
            <Checkbox
                label={t('learn.quiz.shuffle')}
                checked={data.shuffle}
                onCheckedChange={(c) => setData({ ...data, shuffle: c === true })}
            />
            {data.questions.map((q, i) => (
                <Card key={i} className="bg-surface-muted">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="text-fg font-semibold">
                            {t('learn.quiz.question', { number: i + 1 })}{' '}
                            {q.aiDrafted && <Badge tone="warning">{t('learn.author.ai_drafted')}</Badge>}
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            aria-label={`${t('work.delete')} ${i + 1}`}
                            onClick={() => setData({ ...data, questions: data.questions.filter((_, j) => j !== i) })}
                        >
                            <Trash2 className="size-4" aria-hidden />
                        </Button>
                    </div>
                    <div className="mt-2 grid gap-3 sm:grid-cols-[1fr_12rem]">
                        <Field label={t('learn.quiz.prompt')}>
                            <Input
                                value={q.prompt}
                                onChange={(e) => setQuestion(i, { prompt: e.target.value, aiDrafted: false })}
                            />
                        </Field>
                        <Field label={t('learn.quiz.type')}>
                            <Select
                                value={q.kind}
                                onChange={(e) =>
                                    setQuestion(
                                        i,
                                        e.target.value === 'truefalse'
                                            ? {
                                                  kind: 'truefalse',
                                                  options: [
                                                      { text: t('learn.quiz.true'), correct: true },
                                                      { text: t('learn.quiz.false'), correct: false },
                                                  ],
                                              }
                                            : { kind: e.target.value },
                                    )
                                }
                            >
                                {['single', 'multiple', 'truefalse'].map((k) => (
                                    <option key={k} value={k}>
                                        {t(`learn.quiz.type_${k}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>
                    <fieldset className="mt-2 flex flex-col gap-2">
                        <legend className="text-fg text-sm font-semibold">{t('learn.quiz.answers')}</legend>
                        {q.options.map((o, oi) => (
                            <div key={oi} className="flex flex-wrap items-center gap-2">
                                <Checkbox
                                    label={t('learn.quiz.correct')}
                                    checked={o.correct}
                                    onCheckedChange={(c) =>
                                        setQuestion(i, {
                                            options: q.options.map((x, j) =>
                                                q.kind === 'multiple'
                                                    ? j === oi
                                                        ? { ...x, correct: c === true }
                                                        : x
                                                    : { ...x, correct: j === oi },
                                            ),
                                        })
                                    }
                                />
                                <Input
                                    aria-label={`${t('learn.quiz.answer')} ${oi + 1}`}
                                    className="min-w-48 flex-1"
                                    value={o.text}
                                    onChange={(e) =>
                                        setQuestion(i, {
                                            options: q.options.map((x, j) =>
                                                j === oi ? { ...x, text: e.target.value } : x,
                                            ),
                                        })
                                    }
                                />
                                {q.kind !== 'truefalse' && q.options.length > 2 && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={t('work.delete')}
                                        onClick={() =>
                                            setQuestion(i, { options: q.options.filter((_, j) => j !== oi) })
                                        }
                                    >
                                        <Trash2 className="size-4" aria-hidden />
                                    </Button>
                                )}
                            </div>
                        ))}
                        {q.kind !== 'truefalse' && q.options.length < 6 && (
                            <Button
                                size="sm"
                                variant="ghost"
                                className="self-start"
                                onClick={() =>
                                    setQuestion(i, { options: [...q.options, { text: '', correct: false }] })
                                }
                            >
                                + {t('learn.quiz.add_answer')}
                            </Button>
                        )}
                    </fieldset>
                    <Field label={t('learn.quiz.explanation')} className="mt-2">
                        <Input
                            value={q.explanation ?? ''}
                            onChange={(e) => setQuestion(i, { explanation: e.target.value })}
                        />
                    </Field>
                </Card>
            ))}
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="secondary"
                    icon={<Plus className="size-4" aria-hidden />}
                    onClick={() => setData({ ...data, questions: [...data.questions, blank()] })}
                >
                    {t('learn.quiz.add_question')}
                </Button>
                {ai && (
                    <Button
                        variant="secondary"
                        icon={<Sparkles className="size-4" aria-hidden />}
                        loading={busy}
                        onClick={suggest}
                    >
                        {t('learn.quiz.suggest')}
                    </Button>
                )}
                <Button onClick={save}>{t('learn.quiz.save')}</Button>
            </div>
            {message && <p className="text-danger-text text-sm">{message}</p>}
        </div>
    );
}

export interface BuilderAssignment {
    instructions: string;
    rubric: string[];
    evidence: string[];
    maxResubmissions: number;
}

export function AssignmentEditor({ assignment, action }: { assignment: BuilderAssignment; action: string }) {
    const { t } = useTranslation();
    const [data, setData] = useState<BuilderAssignment>(assignment);

    return (
        <div className="flex flex-col gap-4">
            <Field label={t('learn.assignment.instructions')} required>
                <textarea
                    className="border-line bg-surface text-fg min-h-32 rounded-lg border p-3"
                    value={data.instructions}
                    onChange={(e) => setData({ ...data, instructions: e.target.value })}
                />
            </Field>
            <fieldset className="flex flex-col gap-2">
                <legend className="text-fg text-sm font-semibold">{t('learn.assignment.criteria')}</legend>
                {data.rubric.map((r, i) => (
                    <Input
                        key={i}
                        aria-label={`${t('learn.assignment.criteria')} ${i + 1}`}
                        value={r}
                        onChange={(e) =>
                            setData({ ...data, rubric: data.rubric.map((x, j) => (j === i ? e.target.value : x)) })
                        }
                    />
                ))}
                <Button
                    size="sm"
                    variant="ghost"
                    className="self-start"
                    onClick={() => setData({ ...data, rubric: [...data.rubric, ''] })}
                >
                    + {t('learn.assignment.add_criterion')}
                </Button>
            </fieldset>
            <fieldset>
                <legend className="text-fg text-sm font-semibold">{t('learn.assignment.evidence_types')}</legend>
                {['text', 'photo', 'pdf'].map((e) => (
                    <Checkbox
                        key={e}
                        label={t(`learn.assignment.evidence.${e}`)}
                        checked={data.evidence.includes(e)}
                        onCheckedChange={(c) =>
                            setData({
                                ...data,
                                evidence: c === true ? [...data.evidence, e] : data.evidence.filter((x) => x !== e),
                            })
                        }
                    />
                ))}
            </fieldset>
            <Field label={t('learn.assignment.resubmissions')} className="max-w-48">
                <Input
                    type="number"
                    min={0}
                    max={5}
                    value={data.maxResubmissions}
                    onChange={(e) => setData({ ...data, maxResubmissions: Number(e.target.value) })}
                />
            </Field>
            <div>
                <Button
                    onClick={() =>
                        router.put(
                            action,
                            {
                                instructions: data.instructions,
                                rubric: data.rubric,
                                evidence: data.evidence,
                                max_resubmissions: data.maxResubmissions,
                            },
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('work.save')}
                </Button>
            </div>
        </div>
    );
}
