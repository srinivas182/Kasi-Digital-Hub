import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Link2, MessageCircle } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle, EmptyState } from '@/components/ui/display';
import { RadioGroup } from '@/components/ui/RadioGroup';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { postJson } from '../components/postJson';
import { Suggestion } from '../components/Suggestion';

interface Preview {
    name: string;
    phone: string;
    email: string | null;
    town: string | null;
    age: number | null;
    headline: string | null;
    summary: string | null;
    experience: { title: string; organisation: string | null; period: string | null; bullets: string[] }[];
    education: {
        name: string;
        institution: string | null;
        year: number | null;
        inProgress: boolean;
        verified: boolean;
    }[];
    skills: string[];
    languages: { language: string; level: string }[];
}

interface Version {
    id: string;
    template: string;
    createdAt: string;
    code: string | null;
    links: { id: string; expiresAt: string; active: boolean; views: number }[];
}

interface Props {
    person: { name: string; assisted: boolean };
    ready: boolean;
    completeness: number;
    preview: Preview;
    templates: string[];
    shareUrl: string | null;
    versions: Version[];
}

export default function Cv({ person, ready, preview, templates, shareUrl, versions }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const create = useForm({ template: templates[0] ?? 'classic' });
    const [busy, setBusy] = useState(false);
    const [suggestion, setSuggestion] = useState<{ summary: string; warnings: string[] } | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const improve = async () => {
        setBusy(true);
        setMessage(null);
        const result = await postJson<{ ok: boolean; summary?: string; warnings?: string[]; message?: string }>(
            '/work/cv/summary/suggest',
        ).catch(() => ({ ok: false, message: t('work.ai.fallback.unavailable') }));
        setBusy(false);
        if (result.ok && 'summary' in result && result.summary)
            setSuggestion({ summary: result.summary, warnings: result.warnings ?? [] });
        else setMessage(result.message ?? t('work.ai.fallback.unavailable'));
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.cv.title')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('work.cv.title')}</h1>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {!ready ? (
                <div className="mt-6">
                    <EmptyState
                        title={t('work.cv.not_ready')}
                        action={
                            <Link href="/work/profile" className={buttonVariants({})}>
                                {t('work.cv.go_profile')}
                            </Link>
                        }
                    />
                </div>
            ) : (
                <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <Card>
                        <CardTitle>{t('work.cv.preview')}</CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">{t('work.cv.never')}</p>
                        <div className="border-line mt-4 rounded-lg border p-4">
                            <p className="text-fg text-xl font-bold">{preview.name}</p>
                            {preview.headline && <p className="text-primary font-semibold">{preview.headline}</p>}
                            <p className="text-fg-muted text-sm">
                                {[preview.phone, preview.email, preview.town, preview.age ? `Age ${preview.age}` : null]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            <h2 className="text-fg mt-4 text-sm font-bold uppercase">{t('work.cv.summary')}</h2>
                            <p className="text-fg text-sm">{preview.summary}</p>
                            {suggestion ? (
                                <div className="mt-2">
                                    <Suggestion
                                        warnings={suggestion.warnings}
                                        onUse={() =>
                                            router.put(
                                                '/work/cv/summary',
                                                { summary: suggestion.summary },
                                                { preserveScroll: true, onSuccess: () => setSuggestion(null) },
                                            )
                                        }
                                        onDismiss={() => setSuggestion(null)}
                                    >
                                        {suggestion.summary}
                                    </Suggestion>
                                </div>
                            ) : (
                                <Button size="sm" variant="secondary" className="mt-2" loading={busy} onClick={improve}>
                                    {t('work.ai.help_summary')}
                                </Button>
                            )}
                            {message && <p className="text-fg mt-2 text-sm">{message}</p>}
                            {preview.experience.length > 0 && (
                                <>
                                    <h2 className="text-fg mt-4 text-sm font-bold uppercase">
                                        {t('work.steps.experience')}
                                    </h2>
                                    {preview.experience.map((e) => (
                                        <div key={`${e.title}-${e.period}`} className="mt-2">
                                            <p className="text-fg text-sm font-semibold">
                                                {e.title}
                                                {e.organisation ? `, ${e.organisation}` : ''}{' '}
                                                <span className="text-fg-muted font-normal">{e.period}</span>
                                            </p>
                                            <ul className="text-fg list-disc pl-5 text-sm">
                                                {e.bullets.map((b) => (
                                                    <li key={b}>{b}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </>
                            )}
                            {preview.education.length > 0 && (
                                <>
                                    <h2 className="text-fg mt-4 text-sm font-bold uppercase">
                                        {t('work.steps.education')}
                                    </h2>
                                    {preview.education.map((e) => (
                                        <p key={e.name} className="text-fg mt-1 text-sm">
                                            {e.name}{' '}
                                            {e.verified && <Badge tone="success">{t('work.edu.verified')}</Badge>}{' '}
                                            <span className="text-fg-muted">
                                                {[e.institution, e.inProgress ? t('work.edu.in_progress') : e.year]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </span>
                                        </p>
                                    ))}
                                </>
                            )}
                            {preview.skills.length > 0 && (
                                <p className="text-fg mt-4 text-sm">{preview.skills.join(' · ')}</p>
                            )}
                        </div>
                        <form
                            className="mt-4 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                create.post('/work/cv', { preserveScroll: true });
                            }}
                        >
                            <RadioGroup
                                legend={t('work.cv.template')}
                                value={create.data.template}
                                onValueChange={(v) => create.setData('template', v)}
                                options={templates.map((tpl) => ({ value: tpl, label: t(`work.cv.template.${tpl}`) }))}
                            />
                            <div>
                                <Button type="submit" loading={create.processing}>
                                    {t('work.cv.create')}
                                </Button>
                            </div>
                        </form>
                    </Card>

                    <Card>
                        <CardTitle>{t('work.cv.versions')}</CardTitle>
                        {shareUrl && (
                            <div className="mt-3">
                                <Alert tone="success" title={t('work.cv.share_ready')}>
                                    <code className="break-all">{shareUrl}</code>
                                    <a
                                        href={`https://wa.me/?text=${encodeURIComponent(t('work.cv.whatsapp_text', { url: shareUrl }))}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={buttonVariants({ size: 'sm', className: 'mt-2' })}
                                    >
                                        <MessageCircle className="size-4" aria-hidden /> {t('work.cv.whatsapp')}
                                    </a>
                                </Alert>
                            </div>
                        )}
                        {versions.length === 0 && <p className="text-fg-muted mt-3 text-sm">{t('work.cv.none')}</p>}
                        <ul className="mt-3 flex flex-col gap-4" aria-label={t('work.cv.versions')}>
                            {versions.map((v) => (
                                <li key={v.id} className="border-line border-b pb-3 last:border-0">
                                    <p className="text-fg text-sm font-semibold">
                                        {t(`work.cv.template.${v.template}`)} · {formatDateTime(v.createdAt)}
                                    </p>
                                    {v.code && (
                                        <p className="text-fg-muted text-xs">
                                            {t('work.cv.code')}: <span className="font-mono">{v.code}</span>
                                        </p>
                                    )}
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        <a
                                            href={`/work/cv/${v.id}/download`}
                                            className={buttonVariants({ size: 'sm', variant: 'secondary' })}
                                        >
                                            <Download className="size-4" aria-hidden /> {t('work.cv.download')}
                                        </a>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            icon={<Link2 className="size-4" aria-hidden />}
                                            onClick={() =>
                                                router.post(`/work/cv/${v.id}/share`, {}, { preserveScroll: true })
                                            }
                                        >
                                            {t('work.cv.share')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => router.delete(`/work/cv/${v.id}`, { preserveScroll: true })}
                                        >
                                            {t('work.delete')}
                                        </Button>
                                    </div>
                                    {v.links.length > 0 && (
                                        <ul
                                            className="mt-2 flex flex-col gap-1 text-xs"
                                            aria-label={t('work.cv.links')}
                                        >
                                            {v.links.map((l) => (
                                                <li key={l.id} className="flex items-center justify-between gap-2">
                                                    <span className="text-fg-muted">
                                                        <Badge tone={l.active ? 'success' : 'neutral'}>
                                                            {l.active ? t('work.cv.active') : t('work.cv.stopped')}
                                                        </Badge>{' '}
                                                        {formatDate(l.expiresAt)} ·{' '}
                                                        {t('work.cv.views', { count: l.views })}
                                                    </span>
                                                    {l.active && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                router.delete(`/work/cv/${v.id}/share/${l.id}`, {
                                                                    preserveScroll: true,
                                                                })
                                                            }
                                                        >
                                                            {t('work.cv.stop')}
                                                        </Button>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
