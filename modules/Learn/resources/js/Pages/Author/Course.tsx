import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { formatBytes } from '../../components/format';
import { STATUS_TONE } from '../../components/status';

interface Props {
    course: {
        id: string;
        slug: string;
        title: string;
        summary: string | null;
        outcomes: string[];
        topic: string;
        level: string;
        hours: string | null;
        prerequisites: string | null;
        language: string;
        minAge: number;
        delivery: string;
        accreditationId: string | null;
        nqfLevel: number | null;
        credits: number | null;
        licence: string;
        attribution: string | null;
        status: string;
        statusReason: string | null;
        changed: boolean;
    };
    modules: {
        id: number;
        title: string;
        lessons: {
            id: string;
            title: string;
            kind: string;
            aiDrafted: boolean;
            preview: boolean;
            bytes: number;
            mediaStatus: string | null;
        }[];
    }[];
    problems: { lesson: string | null; title: string; code: string }[];
    history: { kind: string; body: string | null; at: string; by: string | null }[];
    accreditations: { id: string; body: string; number: string; status: string }[];
    isAdmin: boolean;
    options: {
        topics: string[];
        levels: string[];
        delivery: string[];
        licences: string[];
        languages: string[];
        kinds: string[];
    };
    ai: boolean;
}

export default function AuthorCourse({
    course,
    modules,
    problems,
    history,
    accreditations,
    isAdmin,
    options,
    ai,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({
        title: course.title,
        summary: course.summary ?? '',
        outcomes: course.outcomes.length ? course.outcomes : [''],
        topic: course.topic,
        level: course.level,
        hours: course.hours ?? '',
        prerequisites: course.prerequisites ?? '',
        language: course.language,
        min_age: course.minAge,
        delivery: course.delivery,
        accreditation_id: course.accreditationId ?? '',
        nqf_level: course.nqfLevel ? String(course.nqfLevel) : '',
        credits: course.credits ? String(course.credits) : '',
        licence: course.licence,
        attribution: course.attribution ?? '',
    });
    const newLesson = useForm({ module_id: String(modules[0]?.id ?? ''), title: '', kind: 'text' });
    const [moduleTitle, setModuleTitle] = useState('');
    const [comment, setComment] = useState('');
    const [busy, setBusy] = useState(false);
    const blocking = problems.filter((p) => p.code !== 'long_sentences');
    const total = modules.reduce((sum, m) => sum + m.lessons.reduce((s, l) => s + l.bytes, 0), 0);

    const suggestOutcomes = async () => {
        setBusy(true);
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        const response = await fetch(`/learn/author/courses/${course.id}/assist`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': match?.[1] ? decodeURIComponent(match[1]) : '',
            },
            body: JSON.stringify({ action: 'outcomes', text: `${form.data.title}. ${form.data.summary}` }),
        }).catch(() => null);
        const result = (await response?.json().catch(() => null)) as { ok: boolean; outcomes?: string[] } | null;
        setBusy(false);
        if (result?.ok && result.outcomes) form.setData('outcomes', result.outcomes);
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={course.title} />
            <Link href="/learn/provider" className="text-primary text-sm font-semibold hover:underline">
                ← {t('learn.provider.courses')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{course.title}</h1>
                <span className="flex flex-wrap items-center gap-2">
                    <Badge tone={STATUS_TONE[course.status] ?? 'neutral'}>{t(`learn.status.${course.status}`)}</Badge>
                    {course.changed && <Badge tone="warning">{t('learn.status.changed')}</Badge>}
                    <span className="text-fg-muted text-sm">
                        {t('learn.author.data_total', { size: formatBytes(total) })}
                    </span>
                </span>
            </div>
            {course.statusReason && (
                <div className="mt-3">
                    <Alert tone="warning" title={course.statusReason} />
                </div>
            )}
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.course && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.course} />
                </div>
            )}

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('learn.author.lessons')}</CardTitle>
                        {modules.map((m, mi) => (
                            <section key={m.id} className="border-line mt-4 border-t pt-3" aria-label={m.title}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-fg flex-1 font-semibold">{m.title}</h3>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={t('learn.author.move_up')}
                                        disabled={mi === 0}
                                        onClick={() =>
                                            router.put(
                                                `/learn/author/courses/${course.id}/modules/${m.id}`,
                                                { title: m.title, move: 'up' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <ArrowUp className="size-4" aria-hidden />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={t('learn.author.move_down')}
                                        disabled={mi === modules.length - 1}
                                        onClick={() =>
                                            router.put(
                                                `/learn/author/courses/${course.id}/modules/${m.id}`,
                                                { title: m.title, move: 'down' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <ArrowDown className="size-4" aria-hidden />
                                    </Button>
                                    {modules.length > 1 && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            aria-label={`${t('work.delete')}: ${m.title}`}
                                            onClick={() =>
                                                router.delete(`/learn/author/courses/${course.id}/modules/${m.id}`, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            <Trash2 className="size-4" aria-hidden />
                                        </Button>
                                    )}
                                </div>
                                <ol className="mt-2 flex flex-col gap-1">
                                    {m.lessons.map((l) => (
                                        <li
                                            key={l.id}
                                            className="flex flex-wrap items-center justify-between gap-2 text-sm"
                                        >
                                            <Link
                                                href={`/learn/author/courses/${course.id}/lessons/${l.id}`}
                                                className="text-primary font-medium hover:underline"
                                            >
                                                {l.title}
                                            </Link>
                                            <span className="flex flex-wrap gap-1">
                                                <Badge>{t(`learn.kind.${l.kind}`)}</Badge>
                                                {l.aiDrafted && (
                                                    <Badge tone="warning">{t('learn.author.ai_drafted')}</Badge>
                                                )}
                                                {l.preview && <Badge tone="primary">{t('learn.author.preview')}</Badge>}
                                                {l.mediaStatus === 'processing' && (
                                                    <Badge tone="warning">{t('learn.media.processing')}</Badge>
                                                )}
                                                <span className="text-fg-muted">{formatBytes(l.bytes)}</span>
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        ))}
                        <form
                            className="border-line mt-4 grid gap-2 border-t pt-4 sm:grid-cols-[1fr_10rem_10rem_auto] sm:items-end"
                            onSubmit={(e) => {
                                e.preventDefault();
                                newLesson.post(`/learn/author/courses/${course.id}/lessons`);
                            }}
                        >
                            <Field label={t('learn.author.lesson_title')} error={newLesson.errors.title}>
                                <Input
                                    value={newLesson.data.title}
                                    onChange={(e) => newLesson.setData('title', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.author.kind')}>
                                <Select
                                    value={newLesson.data.kind}
                                    onChange={(e) => newLesson.setData('kind', e.target.value)}
                                >
                                    {options.kinds.map((k) => (
                                        <option key={k} value={k}>
                                            {t(`learn.kind.${k}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('learn.author.module')}>
                                <Select
                                    value={newLesson.data.module_id}
                                    onChange={(e) => newLesson.setData('module_id', e.target.value)}
                                >
                                    {modules.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.title}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Button
                                type="submit"
                                icon={<Plus className="size-4" aria-hidden />}
                                disabled={!newLesson.data.title.trim()}
                            >
                                {t('learn.author.add_lesson')}
                            </Button>
                        </form>
                        <form
                            className="mt-3 flex flex-wrap items-end gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.post(
                                    `/learn/author/courses/${course.id}/modules`,
                                    { title: moduleTitle },
                                    { preserveScroll: true, onSuccess: () => setModuleTitle('') },
                                );
                            }}
                        >
                            <Field label={t('learn.author.module_title')} className="flex-1">
                                <Input value={moduleTitle} onChange={(e) => setModuleTitle(e.target.value)} />
                            </Field>
                            <Button type="submit" variant="secondary" disabled={!moduleTitle.trim()}>
                                {t('learn.author.add_module')}
                            </Button>
                        </form>
                    </Card>

                    <Card>
                        <CardTitle>{t('learn.author.details')}</CardTitle>
                        <form
                            className="mt-4 grid gap-4 md:grid-cols-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.transform((d) => ({
                                    ...d,
                                    outcomes: d.outcomes.filter((o) => o.trim()),
                                    hours: d.hours || null,
                                    accreditation_id: d.accreditation_id || null,
                                    nqf_level: d.nqf_level || null,
                                    credits: d.credits || null,
                                }));
                                form.put(`/learn/author/courses/${course.id}`, { preserveScroll: true });
                            }}
                        >
                            <Field
                                label={t('learn.author.title')}
                                error={form.errors.title}
                                className="md:col-span-2"
                                required
                            >
                                <Input
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                />
                            </Field>
                            <Field
                                label={t('learn.author.summary')}
                                error={form.errors.summary}
                                className="md:col-span-2"
                            >
                                <Textarea
                                    rows={3}
                                    value={form.data.summary}
                                    onChange={(e) => form.setData('summary', e.target.value)}
                                />
                            </Field>
                            <fieldset className="md:col-span-2">
                                <legend className="text-fg text-sm font-semibold">{t('learn.author.outcomes')}</legend>
                                {form.data.outcomes.map((o, i) => (
                                    <Input
                                        key={i}
                                        className="mt-2"
                                        aria-label={`${t('learn.author.outcomes')} ${i + 1}`}
                                        value={o}
                                        onChange={(e) =>
                                            form.setData(
                                                'outcomes',
                                                form.data.outcomes.map((x, j) => (j === i ? e.target.value : x)),
                                            )
                                        }
                                    />
                                ))}
                                <div className="mt-2 flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => form.setData('outcomes', [...form.data.outcomes, ''])}
                                    >
                                        + {t('learn.author.add_outcome')}
                                    </Button>
                                    {ai && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            icon={<Sparkles className="size-4" aria-hidden />}
                                            loading={busy}
                                            disabled={!form.data.summary.trim()}
                                            onClick={suggestOutcomes}
                                        >
                                            {t('learn.author.suggest_outcomes')}
                                        </Button>
                                    )}
                                </div>
                            </fieldset>
                            {(
                                [
                                    ['topic', options.topics, 'learn.topic.'],
                                    ['level', options.levels, 'learn.level.'],
                                    ['delivery', options.delivery, 'learn.delivery.'],
                                    ['licence', options.licences, 'learn.licence.'],
                                ] as const
                            ).map(([key, values, prefix]) => (
                                <Field key={key} label={t(`learn.author.${key}`)} error={form.errors[key]}>
                                    <Select value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)}>
                                        {values.map((v) => (
                                            <option key={v} value={v}>
                                                {t(`${prefix}${v}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            ))}
                            <Field label={t('learn.author.language')}>
                                <Select
                                    value={form.data.language}
                                    onChange={(e) => form.setData('language', e.target.value)}
                                >
                                    {options.languages.map((l) => (
                                        <option key={l} value={l}>
                                            {l}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('learn.author.min_age')}>
                                <Select
                                    value={String(form.data.min_age)}
                                    onChange={(e) => form.setData('min_age', Number(e.target.value))}
                                >
                                    <option value="16">{t('learn.age.16')}</option>
                                    <option value="18">{t('learn.age.18')}</option>
                                </Select>
                            </Field>
                            <Field label={t('learn.author.hours')} error={form.errors.hours}>
                                <Input
                                    type="number"
                                    step="0.5"
                                    value={form.data.hours}
                                    onChange={(e) => form.setData('hours', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.author.prerequisites')} error={form.errors.prerequisites}>
                                <Input
                                    value={form.data.prerequisites}
                                    onChange={(e) => form.setData('prerequisites', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.author.accreditation')} error={form.errors.accreditation_id}>
                                <Select
                                    value={form.data.accreditation_id}
                                    onChange={(e) => form.setData('accreditation_id', e.target.value)}
                                >
                                    <option value="">{t('learn.author.not_accredited')}</option>
                                    {accreditations.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.body} {a.number} ({t(`learn.accreditation.${a.status}`)})
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {form.data.accreditation_id && (
                                <>
                                    <Field label={t('learn.author.nqf')} error={form.errors.nqf_level}>
                                        <Input
                                            type="number"
                                            value={form.data.nqf_level}
                                            onChange={(e) => form.setData('nqf_level', e.target.value)}
                                        />
                                    </Field>
                                    <Field label={t('learn.author.credits')} error={form.errors.credits}>
                                        <Input
                                            type="number"
                                            value={form.data.credits}
                                            onChange={(e) => form.setData('credits', e.target.value)}
                                        />
                                    </Field>
                                </>
                            )}
                            <Field
                                label={t('learn.author.attribution')}
                                error={form.errors.attribution}
                                className="md:col-span-2"
                            >
                                <Input
                                    value={form.data.attribution}
                                    onChange={(e) => form.setData('attribution', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" loading={form.processing}>
                                    {t('work.save')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('learn.author.checks')}</CardTitle>
                        {problems.length === 0 ? (
                            <p className="text-fg mt-2 text-sm">{t('learn.author.no_problems')}</p>
                        ) : (
                            <ul className="mt-2 flex flex-col gap-1 text-sm">
                                {problems.map((p, i) => (
                                    <li
                                        key={i}
                                        className={p.code === 'long_sentences' ? 'text-fg-muted' : 'text-danger-text'}
                                    >
                                        {p.lesson ? (
                                            <Link
                                                href={`/learn/author/courses/${course.id}/lessons/${p.lesson}`}
                                                className="underline"
                                            >
                                                {p.title}
                                            </Link>
                                        ) : (
                                            p.title
                                        )}
                                        : {t(`learn.problem.${p.code}`)}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {['draft', 'published', 'unpublished'].includes(course.status) &&
                            (course.status !== 'published' || course.changed) && (
                                <Button
                                    className="mt-4"
                                    disabled={blocking.length > 0}
                                    onClick={() =>
                                        router.post(
                                            `/learn/author/courses/${course.id}/submit`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('learn.author.submit')}
                                </Button>
                            )}
                        {isAdmin && course.status === 'submitted' && (
                            <div className="mt-4 flex flex-col gap-2">
                                <Button
                                    onClick={() =>
                                        router.post(
                                            `/learn/author/courses/${course.id}/decide`,
                                            { decision: 'approve' },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('learn.author.approve')}
                                </Button>
                                <Field label={t('learn.author.comment')}>
                                    <Textarea rows={2} value={comment} onChange={(e) => setComment(e.target.value)} />
                                </Field>
                                <Button
                                    variant="secondary"
                                    disabled={!comment.trim()}
                                    onClick={() =>
                                        router.post(
                                            `/learn/author/courses/${course.id}/decide`,
                                            { decision: 'changes', comment },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('learn.author.send_back')}
                                </Button>
                            </div>
                        )}
                        {course.status === 'published' && (
                            <Link
                                href={`/learn/courses/${course.slug}`}
                                className="text-primary mt-3 block text-sm font-semibold hover:underline"
                            >
                                {t('learn.author.view_catalogue')}
                            </Link>
                        )}
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
