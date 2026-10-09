import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { Switch } from '@/components/ui/Switch';
import { AppLayout } from '@/layouts/AppLayout';
import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

import { postJson } from '../components/postJson';
import { Suggestion } from '../components/Suggestion';
import { VoiceButton } from '../components/VoiceButton';

interface Experience {
    id: string;
    kind: string;
    title: string;
    organisation: string | null;
    place: string | null;
    started: string | null;
    ended: string | null;
    duration: string | null;
    description: string | null;
    bullets: string[];
    suggestedBullets: string[];
}

interface Education {
    id: string;
    kind: string;
    name: string;
    institution: string | null;
    year: number | null;
    inProgress: boolean;
    details: string | null;
    documentId: string | null;
}

interface Props {
    step: string;
    person: { name: string; assisted: boolean };
    profile: {
        headline: string | null;
        summary: string | null;
        driversLicence: string;
        ownTransport: boolean;
        workTypes: string[];
        sectors: string[];
        maxTravelKm: number | null;
        availableFrom: string;
        showAge: boolean;
        completeness: number;
    };
    experiences: Experience[];
    education: Education[];
    skills: string[];
    languages: { language: string; level: string }[];
    documents: { id: string; type: string; status: string }[];
    jobMatchingConsent: boolean;
    options: {
        kinds: string[];
        educationKinds: string[];
        workTypes: string[];
        sectors: string[];
        licences: string[];
        languages: string[];
        skillSuggestions: string[];
    };
    ai: { writer: boolean; voiceLanguages: string[] };
}

const STEPS = ['about', 'experience', 'education', 'skills', 'looking_for', 'visibility'];

function toggle(list: string[], value: string) {
    return list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
}

export default function Profile(props: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const { step, person, profile } = props;
    const index = STEPS.indexOf(step);
    const go = (s: string) => router.get('/work/profile', { step: s }, { preserveScroll: false });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.profile.title')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('work.profile.title')}</h1>
                <div className="w-full sm:w-64">
                    <ProgressBar
                        value={profile.completeness}
                        label={t('work.profile.complete', { percent: profile.completeness })}
                    />
                </div>
            </div>
            <nav aria-label={t('work.profile.title')} className="mt-4 overflow-x-auto">
                <ol className="flex gap-2 pb-1">
                    {STEPS.map((s, i) => (
                        <li key={s}>
                            <Link
                                href={`/work/profile?step=${s}`}
                                aria-current={s === step ? 'step' : undefined}
                                className={cn(
                                    'inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold whitespace-nowrap',
                                    s === step
                                        ? 'bg-primary text-primary-fg border-transparent'
                                        : 'border-line text-fg hover:bg-surface-muted',
                                )}
                            >
                                {i + 1}. {t(`work.steps.${s}`)}
                            </Link>
                        </li>
                    ))}
                </ol>
            </nav>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 max-w-3xl">
                {step === 'about' && <AboutStep {...props} />}
                {step === 'experience' && <ExperienceStep {...props} />}
                {step === 'education' && <EducationStep {...props} />}
                {step === 'skills' && <SkillsStep {...props} />}
                {step === 'looking_for' && <LookingForStep {...props} />}
                {step === 'visibility' && <VisibilityStep {...props} />}
                {index > 0 && index < 5 && (
                    <div className="mt-4 flex justify-between">
                        <Button variant="ghost" onClick={() => go(STEPS[index - 1] ?? 'about')}>
                            ← {t(`work.steps.${STEPS[index - 1]}`)}
                        </Button>
                        <Button variant="ghost" onClick={() => go(STEPS[index + 1] ?? 'visibility')}>
                            {t('work.skip')} →
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function AboutStep({ profile, options, ai }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        headline: profile.headline ?? '',
        summary: profile.summary ?? '',
        drivers_licence: profile.driversLicence,
        own_transport: profile.ownTransport,
        show_age_on_cv: profile.showAge,
    });

    return (
        <Card>
            <form
                className="flex flex-col gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/work/profile/about');
                }}
            >
                <Field
                    label={t('work.about.headline')}
                    hint={t('work.about.headline_hint')}
                    error={form.errors.headline}
                    required
                >
                    <Input
                        value={form.data.headline}
                        onChange={(e) => form.setData('headline', e.target.value)}
                        maxLength={120}
                    />
                </Field>
                <Field
                    label={t('work.about.summary')}
                    hint={t('work.about.summary_hint')}
                    error={form.errors.summary}
                    required
                >
                    <Textarea
                        rows={4}
                        value={form.data.summary}
                        onChange={(e) => form.setData('summary', e.target.value)}
                        maxLength={1200}
                    />
                </Field>
                <VoiceButton
                    languages={ai.voiceLanguages}
                    onText={(text) => form.setData('summary', `${form.data.summary} ${text}`.trim())}
                />
                <Field label={t('work.about.licence')} error={form.errors.drivers_licence}>
                    <Select
                        value={form.data.drivers_licence}
                        onChange={(e) => form.setData('drivers_licence', e.target.value)}
                    >
                        {options.licences.map((l) => (
                            <option key={l} value={l}>
                                {l === 'none' ? t('work.about.licence_none') : `Code ${l}`}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Checkbox
                    label={t('work.about.transport')}
                    checked={form.data.own_transport}
                    onCheckedChange={(c) => form.setData('own_transport', c === true)}
                />
                <Checkbox
                    label={t('work.about.show_age')}
                    checked={form.data.show_age_on_cv}
                    onCheckedChange={(c) => form.setData('show_age_on_cv', c === true)}
                />
                <div>
                    <Button type="submit" loading={form.processing}>
                        {t('work.next')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

function ExperienceForm({
    experience,
    options,
    ai,
    onDone,
}: { experience?: Experience; onDone: () => void } & Pick<Props, 'options' | 'ai'>) {
    const { t } = useTranslation();
    const form = useForm({
        kind: experience?.kind ?? 'job',
        title: experience?.title ?? '',
        organisation: experience?.organisation ?? '',
        place: experience?.place ?? '',
        started: experience?.started ?? '',
        ended: experience?.ended ?? '',
        duration: experience?.duration ?? '',
        description: experience?.description ?? '',
    });
    const submit = () => {
        form.transform((d) => ({
            ...d,
            started: d.started || null,
            ended: d.ended || null,
            duration: d.duration || null,
        }));
        if (experience) form.put(`/work/experience/${experience.id}`, { preserveScroll: true, onSuccess: onDone });
        else form.post('/work/experience', { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <Field label={t('work.exp.kind')} error={form.errors.kind}>
                <Select value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                    {options.kinds.map((k) => (
                        <option key={k} value={k}>
                            {t(`work.exp.kind.${k}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('work.exp.title')} hint={t('work.exp.title_hint')} error={form.errors.title} required>
                <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            </Field>
            <Field label={t('work.exp.organisation')} error={form.errors.organisation}>
                <Input value={form.data.organisation} onChange={(e) => form.setData('organisation', e.target.value)} />
            </Field>
            <Field label={t('work.exp.place')} error={form.errors.place}>
                <Input value={form.data.place} onChange={(e) => form.setData('place', e.target.value)} />
            </Field>
            <Field label={t('work.exp.started')} error={form.errors.started}>
                <Input
                    type="month"
                    value={form.data.started}
                    onChange={(e) => form.setData('started', e.target.value)}
                />
            </Field>
            <Field label={t('work.exp.ended')} error={form.errors.ended}>
                <Input type="month" value={form.data.ended} onChange={(e) => form.setData('ended', e.target.value)} />
            </Field>
            <Field
                label={t('work.exp.duration')}
                hint={t('work.exp.duration_hint')}
                error={form.errors.duration}
                className="md:col-span-2"
            >
                <Input value={form.data.duration} onChange={(e) => form.setData('duration', e.target.value)} />
            </Field>
            <Field
                label={t('work.exp.description')}
                hint={t('work.exp.description_hint')}
                error={form.errors.description}
                className="md:col-span-2"
            >
                <Textarea
                    rows={3}
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                />
            </Field>
            <div className="md:col-span-2">
                <VoiceButton
                    languages={ai.voiceLanguages}
                    onText={(text) => form.setData('description', `${form.data.description} ${text}`.trim())}
                />
            </div>
            <div className="flex gap-2 md:col-span-2">
                <Button type="submit" loading={form.processing}>
                    {t('work.save')}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    {t('work.cancel')}
                </Button>
            </div>
        </form>
    );
}

function CvPoints({ experience, ai }: { experience: Experience; ai: Props['ai'] }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const [suggestion, setSuggestion] = useState<{ bullets: string[]; warnings: string[] } | null>(
        experience.suggestedBullets.length ? { bullets: experience.suggestedBullets, warnings: [] } : null,
    );
    const [message, setMessage] = useState<string | null>(null);

    const ask = async () => {
        setBusy(true);
        setMessage(null);
        const result = await postJson<{ ok: boolean; bullets?: string[]; warnings?: string[]; message?: string }>(
            `/work/experience/${experience.id}/suggest`,
        ).catch(() => ({ ok: false, message: t('work.ai.fallback.unavailable') }));
        setBusy(false);
        if (result.ok && 'bullets' in result && result.bullets)
            setSuggestion({ bullets: result.bullets, warnings: result.warnings ?? [] });
        else setMessage(result.message ?? t('work.ai.fallback.unavailable'));
    };
    const accept = (bullets: string[]) =>
        router.put(
            `/work/experience/${experience.id}/bullets`,
            { bullets },
            { preserveScroll: true, onSuccess: () => setSuggestion(null) },
        );

    return (
        <div className="mt-3">
            <p className="text-fg text-sm font-semibold">{t('work.exp.cv_points')}</p>
            {experience.bullets.length > 0 ? (
                <ul className="text-fg mt-1 list-disc pl-5 text-sm">
                    {experience.bullets.map((b) => (
                        <li key={b}>{b}</li>
                    ))}
                </ul>
            ) : (
                <p className="text-fg-muted text-sm">{t('work.exp.cv_points_none')}</p>
            )}
            {ai.writer && !suggestion && (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    className="mt-2"
                    loading={busy}
                    onClick={ask}
                    disabled={(experience.description ?? '').trim().length < 10}
                >
                    {t('work.ai.help_points')}
                </Button>
            )}
            {message && <p className="text-fg mt-2 text-sm">{message}</p>}
            {suggestion && (
                <div className="mt-2">
                    <Suggestion
                        warnings={suggestion.warnings}
                        onUse={() => accept(suggestion.bullets)}
                        onDismiss={() => accept(experience.bullets)}
                    >
                        <ul className="list-disc pl-5">
                            {suggestion.bullets.map((b) => (
                                <li key={b}>{b}</li>
                            ))}
                        </ul>
                    </Suggestion>
                </div>
            )}
        </div>
    );
}

function ExperienceStep(props: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<string | null>(null);
    const { experiences, options, ai } = props;

    return (
        <div className="flex flex-col gap-4">
            <p className="text-fg-muted">{t('work.exp.intro')}</p>
            {experiences.length === 0 && editing !== 'new' && <p className="text-fg-muted">{t('work.exp.none')}</p>}
            {experiences.map((e) => (
                <Card key={e.id}>
                    {editing === e.id ? (
                        <ExperienceForm experience={e} options={options} ai={ai} onDone={() => setEditing(null)} />
                    ) : (
                        <>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <CardTitle>{e.title}</CardTitle>
                                    <p className="text-fg-muted text-sm">
                                        {[
                                            t(`work.exp.kind.${e.kind}`),
                                            e.organisation,
                                            e.place,
                                            e.started ? `${e.started} - ${e.ended ?? 'now'}` : e.duration,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <div className="flex gap-1">
                                    <Button size="sm" variant="ghost" onClick={() => setEditing(e.id)}>
                                        {t('work.edit')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`${t('work.delete')}: ${e.title}`}
                                        onClick={() =>
                                            router.delete(`/work/experience/${e.id}`, { preserveScroll: true })
                                        }
                                    >
                                        <Trash2 className="size-4" aria-hidden />
                                    </Button>
                                </div>
                            </div>
                            {e.description && (
                                <p className="text-fg mt-2 text-sm whitespace-pre-line">{e.description}</p>
                            )}
                            <CvPoints experience={e} ai={ai} />
                        </>
                    )}
                </Card>
            ))}
            {editing === 'new' ? (
                <Card>
                    <ExperienceForm options={options} ai={ai} onDone={() => setEditing(null)} />
                </Card>
            ) : (
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        icon={<Plus className="size-4" aria-hidden />}
                        onClick={() => setEditing('new')}
                    >
                        {t('work.exp.add')}
                    </Button>
                    <Button onClick={() => router.get('/work/profile', { step: 'education' })}>{t('work.next')}</Button>
                </div>
            )}
        </div>
    );
}

function EducationForm({
    education,
    options,
    documents,
    onDone,
}: { education?: Education; onDone: () => void } & Pick<Props, 'options' | 'documents'>) {
    const { t } = useTranslation();
    const form = useForm({
        kind: education?.kind ?? 'matric',
        name: education?.name ?? '',
        institution: education?.institution ?? '',
        year: education?.year ? String(education.year) : '',
        in_progress: education?.inProgress ?? false,
        details: education?.details ?? '',
        document_id: education?.documentId ?? '',
    });
    const submit = () => {
        form.transform((d) => ({
            ...d,
            year: d.year === '' ? null : Number(d.year),
            document_id: d.document_id || null,
        }));
        if (education) form.put(`/work/education/${education.id}`, { preserveScroll: true, onSuccess: onDone });
        else form.post('/work/education', { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <Field label={t('work.edu.kind')} error={form.errors.kind}>
                <Select value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                    {options.educationKinds.map((k) => (
                        <option key={k} value={k}>
                            {t(`work.edu.kind.${k}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('work.edu.name')} hint={t('work.edu.name_hint')} error={form.errors.name} required>
                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            </Field>
            <Field label={t('work.edu.institution')} error={form.errors.institution}>
                <Input value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} />
            </Field>
            <Field label={t('work.edu.year')} error={form.errors.year}>
                <Input
                    type="number"
                    inputMode="numeric"
                    value={form.data.year}
                    onChange={(e) => form.setData('year', e.target.value)}
                />
            </Field>
            <div className="md:col-span-2">
                <Checkbox
                    label={t('work.edu.in_progress')}
                    checked={form.data.in_progress}
                    onCheckedChange={(c) => form.setData('in_progress', c === true)}
                />
            </div>
            <Field label={t('work.edu.details')} error={form.errors.details} className="md:col-span-2">
                <Input value={form.data.details} onChange={(e) => form.setData('details', e.target.value)} />
            </Field>
            <Field label={t('work.edu.document')} error={form.errors.document_id} className="md:col-span-2">
                <Select value={form.data.document_id} onChange={(e) => form.setData('document_id', e.target.value)}>
                    <option value="">{t('work.edu.document_none')}</option>
                    {documents.map((d) => (
                        <option key={d.id} value={d.id}>
                            {t(`documents.type.${d.type}`)} ({t(`documents.status.${d.status}`)})
                        </option>
                    ))}
                </Select>
            </Field>
            <div className="flex gap-2 md:col-span-2">
                <Button type="submit" loading={form.processing}>
                    {t('work.save')}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    {t('work.cancel')}
                </Button>
            </div>
        </form>
    );
}

function EducationStep({ education, options, documents }: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<string | null>(null);
    const verified = new Set(documents.filter((d) => d.status === 'verified').map((d) => d.id));

    return (
        <div className="flex flex-col gap-4">
            {education.length === 0 && editing !== 'new' && <p className="text-fg-muted">{t('work.edu.none')}</p>}
            {education.map((e) => (
                <Card key={e.id}>
                    {editing === e.id ? (
                        <EducationForm
                            education={e}
                            options={options}
                            documents={documents}
                            onDone={() => setEditing(null)}
                        />
                    ) : (
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <CardTitle>
                                    {e.name}{' '}
                                    {e.documentId && verified.has(e.documentId) && (
                                        <Badge tone="success">{t('work.edu.verified')}</Badge>
                                    )}
                                </CardTitle>
                                <p className="text-fg-muted text-sm">
                                    {[e.institution, e.inProgress ? t('work.edu.in_progress') : e.year]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                            <div className="flex gap-1">
                                <Button size="sm" variant="ghost" onClick={() => setEditing(e.id)}>
                                    {t('work.edit')}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label={`${t('work.delete')}: ${e.name}`}
                                    onClick={() => router.delete(`/work/education/${e.id}`, { preserveScroll: true })}
                                >
                                    <Trash2 className="size-4" aria-hidden />
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            ))}
            {editing === 'new' ? (
                <Card>
                    <EducationForm options={options} documents={documents} onDone={() => setEditing(null)} />
                </Card>
            ) : (
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        icon={<Plus className="size-4" aria-hidden />}
                        onClick={() => setEditing('new')}
                    >
                        {t('work.edu.add')}
                    </Button>
                    <Button onClick={() => router.get('/work/profile', { step: 'skills' })}>{t('work.next')}</Button>
                </div>
            )}
        </div>
    );
}

function SkillsStep({ skills, languages, options }: Props) {
    const { t } = useTranslation();
    const form = useForm({ skills, languages: languages.map((l) => ({ language: l.language, level: l.level })) });
    const [draft, setDraft] = useState('');
    const add = (skill: string) => {
        const value = skill.trim();
        if (
            value &&
            form.data.skills.length < 20 &&
            !form.data.skills.some((s) => s.toLowerCase() === value.toLowerCase())
        )
            form.setData('skills', [...form.data.skills, value]);
        setDraft('');
    };
    const unused = options.languages.filter((l) => !form.data.languages.some((x) => x.language === l));

    return (
        <Card>
            <form
                className="flex flex-col gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/work/profile/skills');
                }}
            >
                <fieldset>
                    <legend className="text-fg font-semibold">{t('work.skills.label')}</legend>
                    <p className="text-fg-muted text-sm">{t('work.skills.hint')}</p>
                    <ul className="mt-2 flex flex-wrap gap-2" aria-label={t('work.skills.label')}>
                        {form.data.skills.map((s) => (
                            <li key={s}>
                                <span className="bg-primary-soft text-fg inline-flex items-center gap-1 rounded-full py-1 pr-1 pl-3 text-sm">
                                    {s}
                                    <button
                                        type="button"
                                        aria-label={`${t('work.delete')}: ${s}`}
                                        className="hover:bg-surface-muted grid size-7 place-items-center rounded-full"
                                        onClick={() =>
                                            form.setData(
                                                'skills',
                                                form.data.skills.filter((x) => x !== s),
                                            )
                                        }
                                    >
                                        <X className="size-3.5" aria-hidden />
                                    </button>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-2 flex gap-2">
                        <Input
                            aria-label={t('work.skills.add')}
                            value={draft}
                            list="skill-ideas"
                            onChange={(e) => setDraft(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    add(draft);
                                }
                            }}
                        />
                        <datalist id="skill-ideas">
                            {options.skillSuggestions.map((s) => (
                                <option key={s} value={s} />
                            ))}
                        </datalist>
                        <Button type="button" variant="secondary" onClick={() => add(draft)}>
                            {t('work.add')}
                        </Button>
                    </div>
                    <p className="text-fg-muted mt-3 text-sm">{t('work.skills.suggestions')}:</p>
                    <div className="mt-1 flex flex-wrap gap-2">
                        {options.skillSuggestions
                            .filter((s) => !form.data.skills.includes(s))
                            .slice(0, 12)
                            .map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    className="border-line text-fg hover:bg-surface-muted min-h-9 rounded-full border px-3 text-sm"
                                    onClick={() => add(s)}
                                >
                                    + {s}
                                </button>
                            ))}
                    </div>
                </fieldset>
                <fieldset>
                    <legend className="text-fg font-semibold">{t('work.lang.label')}</legend>
                    <ul className="mt-2 flex flex-col gap-2">
                        {form.data.languages.map((l, i) => (
                            <li key={l.language} className="flex flex-wrap items-center gap-2">
                                <span className="text-fg w-40 text-sm font-medium">{l.language}</span>
                                <Select
                                    aria-label={`${l.language}`}
                                    className="max-w-36"
                                    value={l.level}
                                    onChange={(e) =>
                                        form.setData(
                                            'languages',
                                            form.data.languages.map((x, j) =>
                                                j === i ? { ...x, level: e.target.value } : x,
                                            ),
                                        )
                                    }
                                >
                                    {['basic', 'good', 'fluent'].map((level) => (
                                        <option key={level} value={level}>
                                            {t(`work.lang.level.${level}`)}
                                        </option>
                                    ))}
                                </Select>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    aria-label={`${t('work.delete')}: ${l.language}`}
                                    onClick={() =>
                                        form.setData(
                                            'languages',
                                            form.data.languages.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <X className="size-4" aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                    {unused.length > 0 && (
                        <Select
                            aria-label={t('work.lang.add')}
                            className="mt-2 max-w-60"
                            value=""
                            onChange={(e) =>
                                e.target.value &&
                                form.setData('languages', [
                                    ...form.data.languages,
                                    { language: e.target.value, level: 'good' },
                                ])
                            }
                        >
                            <option value="">+ {t('work.lang.add')}</option>
                            {unused.map((l) => (
                                <option key={l} value={l}>
                                    {l}
                                </option>
                            ))}
                        </Select>
                    )}
                </fieldset>
                <div>
                    <Button type="submit" loading={form.processing}>
                        {t('work.next')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

function LookingForStep({ profile, options }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        work_types: profile.workTypes,
        sectors: profile.sectors,
        max_travel_km: profile.maxTravelKm === null ? '' : String(profile.maxTravelKm),
        available_from: profile.availableFrom,
    });

    return (
        <Card>
            <form
                className="flex flex-col gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((d) => ({
                        ...d,
                        max_travel_km: d.max_travel_km === '' ? null : Number(d.max_travel_km),
                    }));
                    form.put('/work/profile/looking-for');
                }}
            >
                <fieldset>
                    <legend className="text-fg font-semibold">{t('work.look.types')}</legend>
                    <div className="mt-2 grid gap-1 sm:grid-cols-2">
                        {options.workTypes.map((w) => (
                            <Checkbox
                                key={w}
                                label={t(`work.type.${w}`)}
                                checked={form.data.work_types.includes(w)}
                                onCheckedChange={() => form.setData('work_types', toggle(form.data.work_types, w))}
                            />
                        ))}
                    </div>
                </fieldset>
                <fieldset>
                    <legend className="text-fg font-semibold">{t('work.look.sectors')}</legend>
                    {form.errors.sectors && <p className="text-danger-text text-sm">{form.errors.sectors}</p>}
                    <div className="mt-2 grid gap-1 sm:grid-cols-2">
                        {options.sectors.map((s) => (
                            <Checkbox
                                key={s}
                                label={t(`work.sector.${s}`)}
                                checked={form.data.sectors.includes(s)}
                                onCheckedChange={() => form.setData('sectors', toggle(form.data.sectors, s))}
                            />
                        ))}
                    </div>
                </fieldset>
                <Field label={t('work.look.travel')} error={form.errors.max_travel_km}>
                    <Input
                        type="number"
                        inputMode="numeric"
                        className="max-w-32"
                        value={form.data.max_travel_km}
                        onChange={(e) => form.setData('max_travel_km', e.target.value)}
                    />
                </Field>
                <Field label={t('work.look.start')} error={form.errors.available_from}>
                    <Select
                        className="max-w-48"
                        value={form.data.available_from}
                        onChange={(e) => form.setData('available_from', e.target.value)}
                    >
                        {['now', '2_weeks', '1_month'].map((v) => (
                            <option key={v} value={v}>
                                {t(`work.look.start.${v}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div>
                    <Button type="submit" loading={form.processing}>
                        {t('work.next')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

function VisibilityStep({ jobMatchingConsent, person }: Props) {
    const { t } = useTranslation();
    return (
        <Card>
            <p className="text-fg">{t('work.vis.intro')}</p>
            <p className="text-fg mt-3 flex items-center gap-2 font-semibold">
                <Switch
                    label={jobMatchingConsent ? t('work.vis.on') : t('work.vis.off')}
                    checked={jobMatchingConsent}
                    disabled
                />
            </p>
            {!person.assisted && (
                <Link href="/account?tab=privacy" className="text-primary text-sm font-semibold hover:underline">
                    {t('work.vis.change')}
                </Link>
            )}
            <div className="border-line mt-6 flex flex-wrap items-center gap-3 border-t pt-4">
                <CheckCircle2 className="text-success-text size-6" aria-hidden />
                <span className="text-fg">{t('work.vis.done')}</span>
                <Link href="/work/cv" className={buttonVariants({})}>
                    {t('work.vis.go_cv')}
                </Link>
            </div>
        </Card>
    );
}
