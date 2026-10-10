import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { JSONContent } from '@tiptap/react';
import { ArrowDown, ArrowUp, Sparkles, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { Editor } from '../../components/Editor';
import { formatBytes } from '../../components/format';
import { postJson } from '../../components/postJson';

interface Props {
    course: { id: string; title: string; status: string };
    lesson: {
        id: string;
        title: string;
        kind: string;
        content: JSONContent | null;
        transcript: string | null;
        minutes: number | null;
        aiDrafted: boolean;
        preview: boolean;
        bytes: number;
        media: {
            id: string;
            kind: string;
            status: string;
            name: string;
            duration: number | null;
            reason: string | null;
            versions: Record<string, number>;
        } | null;
    };
    problems: string[];
    ai: boolean;
    maxUploadMb: number;
}

export default function AuthorLesson({ course, lesson, problems, ai, maxUploadMb }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const base = `/learn/author/courses/${course.id}`;
    const form = useForm({
        title: lesson.title,
        content: lesson.content,
        transcript: lesson.transcript ?? '',
        minutes: lesson.minutes ? String(lesson.minutes) : '',
        preview: lesson.preview,
        ai_drafted: lesson.aiDrafted,
        media_id: lesson.media?.id ?? null,
    });
    const [editorKey, setEditorKey] = useState(0);
    const [outline, setOutline] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const save = (extra: Record<string, unknown> = {}) => {
        form.transform((d) => ({ ...d, minutes: d.minutes === '' ? null : Number(d.minutes), ...extra }));
        form.put(`${base}/lessons/${lesson.id}`, { preserveScroll: true });
    };

    const upload = async (file: File, kind: string, alt = ''): Promise<{ id: string; url: string } | null> => {
        const data = new FormData();
        data.append('kind', kind);
        data.append('file', file);
        if (alt) data.append('alt', alt);
        setUploading(true);
        const result = await postJson<{ ok: boolean; id?: string; url?: string; message?: string }>(
            `${base}/media`,
            data,
        ).catch(() => ({ ok: false, message: t('ai.fallback.unavailable') }));
        setUploading(false);
        if (result.ok && 'id' in result && result.id && result.url) return { id: result.id, url: result.url };
        setMessage(result.message ?? t('ai.fallback.unavailable'));
        return null;
    };

    const draft = async () => {
        setBusy(true);
        setMessage(null);
        const result = await postJson<{ ok: boolean; doc?: JSONContent; message?: string }>(`${base}/assist`, {
            action: 'draft',
            title: form.data.title,
            text: outline,
        }).catch(() => ({
            ok: false,
            message: t('ai.fallback.unavailable'),
        }));
        setBusy(false);
        if (result.ok && 'doc' in result && result.doc) {
            form.setData((d) => ({ ...d, content: result.doc ?? null, ai_drafted: true }));
            setEditorKey((k) => k + 1);
        } else setMessage(result.message ?? t('ai.fallback.unavailable'));
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={lesson.title} />
            <Link href={base} className="text-primary text-sm font-semibold hover:underline">
                ← {course.title}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{lesson.title}</h1>
                <span className="flex flex-wrap items-center gap-2">
                    <Badge>{t(`learn.kind.${lesson.kind}`)}</Badge>
                    {form.data.ai_drafted && <Badge tone="warning">{t('learn.author.ai_drafted')}</Badge>}
                    <span className="text-fg-muted text-sm">{formatBytes(lesson.bytes)}</span>
                </span>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {(errors?.content || message) && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors?.content ?? message ?? ''} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <Card>
                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                        <Field label={t('learn.author.lesson_title')} error={form.errors.title}>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>
                        <Field label={t('learn.author.minutes')} error={form.errors.minutes}>
                            <Input
                                type="number"
                                value={form.data.minutes}
                                onChange={(e) => form.setData('minutes', e.target.value)}
                            />
                        </Field>
                    </div>
                    {['video', 'audio', 'download'].includes(lesson.kind) && (
                        <div className="border-line mt-4 rounded-lg border p-4">
                            {lesson.media ? (
                                <p className="text-fg text-sm">
                                    {lesson.media.name} ·{' '}
                                    <Badge
                                        tone={
                                            lesson.media.status === 'ready'
                                                ? 'success'
                                                : lesson.media.status === 'failed'
                                                  ? 'danger'
                                                  : 'warning'
                                        }
                                    >
                                        {t(`learn.media.${lesson.media.status}`)}
                                    </Badge>
                                    {lesson.media.reason && (
                                        <span className="text-danger-text">
                                            {' '}
                                            {t(`learn.media.reason.${lesson.media.reason}`)}
                                        </span>
                                    )}
                                    {Object.entries(lesson.media.versions).map(([name, bytes]) => (
                                        <span key={name} className="text-fg-muted ml-2">
                                            {t(`learn.media.version.${name}`)}: {formatBytes(bytes)}
                                        </span>
                                    ))}
                                </p>
                            ) : (
                                <p className="text-fg-muted text-sm">{t('learn.media.none')}</p>
                            )}
                            <label className="text-primary mt-3 inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold">
                                <Upload className="size-4" aria-hidden />
                                {uploading
                                    ? t('learn.media.uploading')
                                    : t('learn.media.upload', { size: maxUploadMb })}
                                <input
                                    type="file"
                                    className="sr-only"
                                    accept={
                                        lesson.kind === 'video'
                                            ? 'video/*'
                                            : lesson.kind === 'audio'
                                              ? 'audio/*'
                                              : 'application/pdf'
                                    }
                                    onChange={async (e) => {
                                        const file = e.target.files?.[0];
                                        e.target.value = '';
                                        if (!file) return;
                                        const media = await upload(file, lesson.kind);
                                        if (media) save({ media_id: media.id });
                                    }}
                                />
                            </label>
                            {lesson.kind === 'video' && (
                                <p className="text-fg-muted text-xs">{t('learn.media.video_hint')}</p>
                            )}
                        </div>
                    )}
                    {['video', 'audio'].includes(lesson.kind) && (
                        <Field
                            label={t('learn.author.transcript')}
                            hint={t('learn.author.transcript_hint')}
                            error={form.errors.transcript}
                            className="mt-4"
                        >
                            <Textarea
                                rows={6}
                                value={form.data.transcript}
                                onChange={(e) => form.setData('transcript', e.target.value)}
                            />
                        </Field>
                    )}
                    {['text', 'video', 'audio', 'download'].includes(lesson.kind) && (
                        <div className="mt-4">
                            <p className="text-fg mb-1 text-sm font-semibold">
                                {lesson.kind === 'text' ? t('learn.author.content') : t('learn.author.notes')}
                            </p>
                            <Editor
                                key={editorKey}
                                label={t('learn.author.content')}
                                value={form.data.content}
                                onChange={(doc) => form.setData((d) => ({ ...d, content: doc, ai_drafted: false }))}
                                onUploadImage={async (file, alt) => (await upload(file, 'image', alt))?.url ?? null}
                            />
                        </div>
                    )}
                    {['quiz', 'assignment'].includes(lesson.kind) && (
                        <p className="text-fg-muted mt-4">{t('learn.author.coming_s14')}</p>
                    )}
                    <div className="mt-4">
                        <Checkbox
                            label={t('learn.author.preview_label')}
                            checked={form.data.preview}
                            onCheckedChange={(c) => form.setData('preview', c === true)}
                        />
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button loading={form.processing} onClick={() => save()}>
                            {t('work.save')}
                        </Button>
                        <Button
                            variant="ghost"
                            aria-label={t('learn.author.move_up')}
                            onClick={() => save({ move: 'up' })}
                        >
                            <ArrowUp className="size-4" aria-hidden />
                        </Button>
                        <Button
                            variant="ghost"
                            aria-label={t('learn.author.move_down')}
                            onClick={() => save({ move: 'down' })}
                        >
                            <ArrowDown className="size-4" aria-hidden />
                        </Button>
                        <Button
                            variant="ghost"
                            icon={<Trash2 className="size-4" aria-hidden />}
                            onClick={() => router.delete(`${base}/lessons/${lesson.id}`)}
                        >
                            {t('work.delete')}
                        </Button>
                    </div>
                </Card>
                <div className="flex flex-col gap-6">
                    {lesson.kind === 'text' && (
                        <Card>
                            <CardTitle>{t('learn.author.checks')}</CardTitle>
                            {problems.length === 0 ? (
                                <p className="text-fg mt-2 text-sm">{t('learn.author.no_problems')}</p>
                            ) : (
                                <ul className="mt-2 list-disc pl-5 text-sm">
                                    {problems.map((p) => (
                                        <li
                                            key={p}
                                            className={p === 'long_sentences' ? 'text-fg-muted' : 'text-danger-text'}
                                        >
                                            {t(`learn.problem.${p}`)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="text-fg-muted mt-2 text-xs">{t('learn.author.checks_after_save')}</p>
                        </Card>
                    )}
                    {ai && lesson.kind === 'text' && (
                        <Card>
                            <CardTitle>
                                <span className="flex items-center gap-2">
                                    <Sparkles className="size-4" aria-hidden /> {t('learn.author.draft_title')}
                                </span>
                            </CardTitle>
                            <Field
                                label={t('learn.author.outline')}
                                hint={t('learn.author.outline_hint')}
                                className="mt-3"
                            >
                                <Textarea rows={5} value={outline} onChange={(e) => setOutline(e.target.value)} />
                            </Field>
                            <Button
                                variant="secondary"
                                className="mt-2"
                                loading={busy}
                                disabled={outline.trim().length < 10}
                                onClick={draft}
                            >
                                {t('learn.author.draft_go')}
                            </Button>
                            <p className="text-fg-muted mt-2 text-xs">{t('learn.author.draft_note')}</p>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
