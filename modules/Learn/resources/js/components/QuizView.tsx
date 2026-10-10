import { useMemo, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { RadioGroup } from '@/components/ui/RadioGroup';
import { useTranslation } from '@/lib/i18n';

export interface QuizQuestion {
    id: number;
    kind: string;
    prompt: string;
    options: { text: string; correct?: boolean; feedback?: string | null }[];
    explanation?: string | null;
}

interface Props {
    questions: QuizQuestion[];
    graded: boolean;
    passMark?: number;
    /** Graded: send to the server and return the result. Practice: marked here. */
    onSubmit: (answers: Record<number, number[]>) => Promise<{
        score: number;
        passed?: boolean;
        results?: { id: number; correct: boolean; explanation: string | null }[];
        message?: string;
    }>;
    disabled?: boolean;
}

/** A quiz: one question per card, accessible radio/checkbox groups, results with explanations. */
export function QuizView({ questions, graded, passMark = 70, onSubmit, disabled }: Props) {
    const { t } = useTranslation();
    const [answers, setAnswers] = useState<Record<number, number[]>>({});
    const [result, setResult] = useState<{
        score: number;
        passed?: boolean;
        results?: { id: number; correct: boolean; explanation: string | null }[];
        message?: string;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const byId = useMemo(() => Object.fromEntries((result?.results ?? []).map((r) => [r.id, r])), [result]);

    const submit = async () => {
        setBusy(true);
        setResult(await onSubmit(answers));
        setBusy(false);
    };

    return (
        <div className="flex flex-col gap-4">
            {questions.map((q, i) => (
                <fieldset key={q.id} className="border-line rounded-lg border p-3">
                    {q.kind === 'multiple' ? (
                        <>
                            <legend className="text-fg px-1 font-semibold">
                                {i + 1}. {q.prompt}{' '}
                                <span className="text-fg-muted text-sm font-normal">
                                    ({t('learn.quiz.choose_all')})
                                </span>
                            </legend>
                            {q.options.map((o, oi) => (
                                <Checkbox
                                    key={oi}
                                    label={o.text}
                                    disabled={!!result}
                                    checked={(answers[q.id] ?? []).includes(oi)}
                                    onCheckedChange={(c) =>
                                        setAnswers({
                                            ...answers,
                                            [q.id]:
                                                c === true
                                                    ? [...(answers[q.id] ?? []), oi]
                                                    : (answers[q.id] ?? []).filter((x) => x !== oi),
                                        })
                                    }
                                />
                            ))}
                        </>
                    ) : (
                        <RadioGroup
                            legend={`${i + 1}. ${q.prompt}`}
                            value={answers[q.id]?.[0] !== undefined ? String(answers[q.id]?.[0]) : ''}
                            onValueChange={(v) => setAnswers({ ...answers, [q.id]: [Number(v)] })}
                            options={q.options.map((o, oi) => ({ value: String(oi), label: o.text }))}
                        />
                    )}
                    {byId[q.id] && (
                        <p
                            className={
                                byId[q.id]?.correct
                                    ? 'text-success-text mt-2 text-sm font-semibold'
                                    : 'text-danger-text mt-2 text-sm font-semibold'
                            }
                        >
                            {byId[q.id]?.correct ? t('learn.quiz.right') : t('learn.quiz.wrong')}{' '}
                            {byId[q.id]?.explanation ?? ''}
                        </p>
                    )}
                </fieldset>
            ))}
            {result?.message && <Alert tone="danger" title={result.message} />}
            {result && !result.message && (
                <Alert
                    tone={result.passed === false ? 'warning' : 'success'}
                    title={t(
                        graded
                            ? result.passed
                                ? 'learn.quiz.passed'
                                : 'learn.quiz.not_passed'
                            : 'learn.quiz.practice_score',
                        { score: result.score, mark: passMark },
                    )}
                />
            )}
            <div className="flex gap-2">
                {!result && (
                    <Button onClick={submit} loading={busy} disabled={disabled || Object.keys(answers).length === 0}>
                        {t('learn.quiz.submit')}
                    </Button>
                )}
                {result && !(graded && result.passed) && (
                    <Button
                        variant="secondary"
                        onClick={() => {
                            setResult(null);
                            setAnswers({});
                        }}
                    >
                        {t('learn.quiz.again')}
                    </Button>
                )}
            </div>
        </div>
    );
}

/** Mark a practice quiz on the phone (practice quizzes include the answers). */
export function markPractice(questions: QuizQuestion[], answers: Record<number, number[]>) {
    const results = questions.map((q) => {
        const correct = q.options.flatMap((o, i) => (o.correct ? [i] : []));
        const chosen = [...(answers[q.id] ?? [])].sort();
        return {
            id: q.id,
            correct: JSON.stringify(chosen) === JSON.stringify(correct),
            explanation: q.explanation ?? null,
        };
    });
    return {
        score: Math.round((100 * results.filter((r) => r.correct).length) / Math.max(1, results.length)),
        results,
    };
}
