import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Sparkles, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { postJson } from '../../components/postJson';

interface Occupation {
    id: number;
    title: string;
}

interface ListingProps {
    id: string;
    status: string;
    reason: string | null;
    title: string;
    occupation: Occupation | null;
    type: string;
    positions: number;
    municipalityId: number | null;
    placeName: string | null;
    payMin: number;
    payMax: number | null;
    payPeriod: string;
    hours: string | null;
    education: string | null;
    licence: string | null;
    experience: string;
    languages: string[];
    description: string;
    closesOn: string;
    skills: { name: string; must: boolean }[];
    questions: { question: string; kind: string }[];
}

interface Props {
    person: { name: string; assisted: boolean };
    employer: { name: string; verified: boolean };
    listing: ListingProps | null;
    cities: { id: number; name: string }[];
    defaultCity: number | null;
    options: {
        types: string[];
        periods: string[];
        education: string[];
        experience: string[];
        licences: string[];
        languages: string[];
        maxDays: number;
        minimumWage: number;
    };
    aiEnabled: boolean;
}

function inDays(days: number) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

export default function ListingEdit({ person, employer, listing, cities, defaultCity, options, aiEnabled }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [occupation, setOccupation] = useState<Occupation | null>(listing?.occupation ?? null);
    const [occupationQuery, setOccupationQuery] = useState('');
    const [occupationResults, setOccupationResults] = useState<Occupation[]>([]);
    const form = useForm({
        title: listing?.title ?? '',
        type: listing?.type ?? 'full_time',
        positions: String(listing?.positions ?? 1),
        municipality_id: String(listing?.municipalityId ?? defaultCity ?? ''),
        place_name: listing?.placeName ?? '',
        pay_min: listing ? String(listing.payMin) : '',
        pay_max: listing?.payMax != null ? String(listing.payMax) : '',
        pay_period: listing?.payPeriod ?? 'hour',
        hours: listing?.hours ?? '',
        education: listing?.education ?? 'none',
        licence: listing?.licence ?? 'none',
        experience: listing?.experience ?? 'none',
        languages: listing?.languages ?? ['English'],
        description: listing?.description ?? '',
        closes_on: listing?.closesOn ?? inDays(21),
        skills: listing?.skills ?? [],
        questions: listing?.questions ?? [],
    });
    const [skillDraft, setSkillDraft] = useState('');
    const [notes, setNotes] = useState('');
    const [writing, setWriting] = useState(false);
    const [writeMessage, setWriteMessage] = useState<string | null>(null);

    useEffect(() => {
        if (occupationQuery.trim().length < 2) return;
        let cancelled = false;
        const handle = setTimeout(() => {
            void fetch(`/work/employer/occupations?q=${encodeURIComponent(occupationQuery)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json() as Promise<Occupation[]>)
                .then((results) => {
                    if (!cancelled) setOccupationResults(results);
                });
        }, 250);
        return () => {
            cancelled = true;
            clearTimeout(handle);
        };
    }, [occupationQuery]);
    const shownOccupations = occupationQuery.trim().length < 2 ? [] : occupationResults;

    const submit = (publish: boolean) => {
        form.transform((d) => ({
            ...d,
            occupation_id: occupation?.id ?? null,
            pay_max: d.pay_max === '' ? null : d.pay_max,
            licence: d.licence === 'none' ? null : d.licence,
            publish,
        }));
        if (listing) form.put(`/work/employer/listings/${listing.id}`, { preserveScroll: true });
        else form.post('/work/employer/listings');
    };

    const write = async () => {
        setWriting(true);
        setWriteMessage(null);
        const result = await postJson<{
            ok: boolean;
            title?: string;
            occupation?: Occupation | null;
            description?: string;
            mustSkills?: string[];
            niceSkills?: string[];
            questions?: string[];
            message?: string;
        }>('/work/employer/listings/write', { notes }).catch(() => ({
            ok: false,
            message: t('ai.fallback.unavailable'),
        }));
        setWriting(false);
        if (!result.ok || !('description' in result)) {
            setWriteMessage(result.message ?? t('ai.fallback.unavailable'));
            return;
        }
        form.setData((d) => ({
            ...d,
            title: result.title || d.title,
            description: result.description || d.description,
            skills: [
                ...(result.mustSkills ?? []).map((name) => ({ name, must: true })),
                ...(result.niceSkills ?? []).map((name) => ({ name, must: false })),
            ],
            questions: (result.questions ?? []).map((question) => ({ question, kind: 'yes_no' })),
        }));
        if (result.occupation) setOccupation(result.occupation);
    };

    const live = listing && ['live', 'review'].includes(listing.status);
    const errorFor = (key: string) => (errors as Record<string, string | undefined>)[key];

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={listing ? t('work.listing.edit') : t('work.listing.new')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">
                        {listing ? t('work.listing.edit') : t('work.listing.new')}
                    </h1>
                    <p className="text-fg-muted text-sm">{employer.name}</p>
                </div>
                {listing && <Badge>{t(`work.listing.status.${listing.status}`)}</Badge>}
            </div>
            {listing?.reason && (
                <div className="mt-4">
                    <Alert tone={listing.status === 'taken_down' ? 'danger' : 'warning'} title={listing.reason} />
                </div>
            )}
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errorFor('publish') && (
                <div className="mt-4">
                    <Alert tone="danger" title={errorFor('publish') ?? ''} />
                </div>
            )}
            {Object.keys(form.errors).length > 0 && (
                <div className="mt-4">
                    <Alert tone="danger" title={t('work.listing.fix_errors')}>
                        <ul className="list-disc pl-5">
                            {Object.entries(form.errors).map(([key, message]) => (
                                <li key={key}>{message}</li>
                            ))}
                        </ul>
                    </Alert>
                </div>
            )}
            {!employer.verified && (
                <div className="mt-4">
                    <Alert tone="info" title={t('work.employer.pending_hint')} />
                </div>
            )}

            <div className="mt-6 grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem]">
                <Card>
                    <form
                        className="grid gap-4 md:grid-cols-2"
                        noValidate
                        onSubmit={(e) => {
                            e.preventDefault();
                            submit(false);
                        }}
                    >
                        <Field
                            label={t('work.listing.title')}
                            error={form.errors.title}
                            required
                            className="md:col-span-2"
                        >
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>
                        <div className="md:col-span-2">
                            <Field label={t('work.listing.occupation')} hint={t('work.listing.occupation_hint')}>
                                <Input value={occupationQuery} onChange={(e) => setOccupationQuery(e.target.value)} />
                            </Field>
                            <p className="text-fg mt-1 text-sm">
                                {occupation ? (
                                    <Badge tone="primary">{occupation.title}</Badge>
                                ) : (
                                    <span className="text-fg-muted">{t('work.listing.occupation_none')}</span>
                                )}
                            </p>
                            {shownOccupations.length > 0 && (
                                <ul className="border-line mt-2 max-h-48 overflow-auto rounded border">
                                    {shownOccupations.map((o) => (
                                        <li key={o.id}>
                                            <button
                                                type="button"
                                                className="hover:bg-surface-muted text-fg block min-h-10 w-full px-3 text-left text-sm"
                                                onClick={() => {
                                                    setOccupation(o);
                                                    setOccupationQuery('');
                                                }}
                                            >
                                                {o.title}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <Field label={t('work.listing.type')} error={form.errors.type}>
                            <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                {options.types.map((v) => (
                                    <option key={v} value={v}>
                                        {t(`work.type.${v}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('work.listing.positions')} error={form.errors.positions}>
                            <Input
                                type="number"
                                min={1}
                                value={form.data.positions}
                                onChange={(e) => form.setData('positions', e.target.value)}
                            />
                        </Field>
                        <Field label={t('work.listing.city')} error={form.errors.municipality_id} required>
                            <Select
                                value={form.data.municipality_id}
                                onChange={(e) => form.setData('municipality_id', e.target.value)}
                            >
                                <option value="">-</option>
                                {cities.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('work.listing.place')} error={form.errors.place_name}>
                            <Input
                                value={form.data.place_name}
                                onChange={(e) => form.setData('place_name', e.target.value)}
                            />
                        </Field>
                        <div className="grid gap-4 md:col-span-2 md:grid-cols-3">
                            <Field label={t('work.listing.pay_min')} error={form.errors.pay_min} required>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.pay_min}
                                    onChange={(e) => form.setData('pay_min', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.listing.pay_max')} error={form.errors.pay_max}>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.pay_max}
                                    onChange={(e) => form.setData('pay_max', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.listing.pay_period')} error={form.errors.pay_period}>
                                <Select
                                    value={form.data.pay_period}
                                    onChange={(e) => form.setData('pay_period', e.target.value)}
                                >
                                    {options.periods.map((p) => (
                                        <option key={p} value={p}>
                                            {t(`work.pay.per_${p}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <p className="text-fg-muted -mt-2 text-xs md:col-span-3">
                                {t('work.listing.pay_hint', { rate: options.minimumWage.toFixed(2) })}
                            </p>
                        </div>
                        <Field label={t('work.listing.hours')} error={form.errors.hours} className="md:col-span-2">
                            <Input value={form.data.hours} onChange={(e) => form.setData('hours', e.target.value)} />
                        </Field>
                        <Field label={t('work.listing.education')} error={form.errors.education}>
                            <Select
                                value={form.data.education}
                                onChange={(e) => form.setData('education', e.target.value)}
                            >
                                {options.education.map((v) => (
                                    <option key={v} value={v}>
                                        {t(`work.education.${v}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('work.listing.experience')} error={form.errors.experience}>
                            <Select
                                value={form.data.experience}
                                onChange={(e) => form.setData('experience', e.target.value)}
                            >
                                {options.experience.map((v) => (
                                    <option key={v} value={v}>
                                        {t(`work.experience.${v}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('work.listing.licence')} error={form.errors.licence}>
                            <Select value={form.data.licence} onChange={(e) => form.setData('licence', e.target.value)}>
                                {options.licences.map((l) => (
                                    <option key={l} value={l}>
                                        {l === 'none' ? t('work.about.licence_none') : `Code ${l}`}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('work.listing.closes_on')} error={form.errors.closes_on} required>
                            <Input
                                type="date"
                                min={inDays(0)}
                                max={inDays(options.maxDays)}
                                value={form.data.closes_on}
                                onChange={(e) => form.setData('closes_on', e.target.value)}
                            />
                        </Field>
                        <fieldset className="md:col-span-2">
                            <legend className="text-fg text-sm font-semibold">{t('work.listing.languages')}</legend>
                            <div className="mt-1 grid gap-1 sm:grid-cols-3">
                                {options.languages.slice(0, 9).map((l) => (
                                    <Checkbox
                                        key={l}
                                        label={l}
                                        checked={form.data.languages.includes(l)}
                                        onCheckedChange={() =>
                                            form.setData(
                                                'languages',
                                                form.data.languages.includes(l)
                                                    ? form.data.languages.filter((x) => x !== l)
                                                    : [...form.data.languages, l],
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>
                        <Field
                            label={t('work.listing.description')}
                            error={form.errors.description}
                            required
                            className="md:col-span-2"
                        >
                            <Textarea
                                rows={6}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <fieldset className="md:col-span-2">
                            <legend className="text-fg text-sm font-semibold">{t('work.listing.skills')}</legend>
                            <ul className="mt-2 flex flex-col gap-2">
                                {form.data.skills.map((s, i) => (
                                    <li key={`${s.name}-${i}`} className="flex flex-wrap items-center gap-3">
                                        <span className="text-fg w-48 text-sm">{s.name}</span>
                                        <Checkbox
                                            label={t('work.listing.must')}
                                            checked={s.must}
                                            onCheckedChange={(c) =>
                                                form.setData(
                                                    'skills',
                                                    form.data.skills.map((x, j) =>
                                                        j === i ? { ...x, must: c === true } : x,
                                                    ),
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            aria-label={`${t('work.delete')}: ${s.name}`}
                                            onClick={() =>
                                                form.setData(
                                                    'skills',
                                                    form.data.skills.filter((_, j) => j !== i),
                                                )
                                            }
                                        >
                                            <X className="size-4" aria-hidden />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-2 flex gap-2">
                                <Input
                                    aria-label={t('work.listing.add_skill')}
                                    value={skillDraft}
                                    onChange={(e) => setSkillDraft(e.target.value)}
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => {
                                        if (skillDraft.trim())
                                            form.setData('skills', [
                                                ...form.data.skills,
                                                { name: skillDraft.trim(), must: true },
                                            ]);
                                        setSkillDraft('');
                                    }}
                                >
                                    {t('work.add')}
                                </Button>
                            </div>
                        </fieldset>
                        <fieldset className="md:col-span-2">
                            <legend className="text-fg text-sm font-semibold">{t('work.listing.questions')}</legend>
                            <p className="text-fg-muted text-xs">{t('work.listing.questions_hint')}</p>
                            <ul className="mt-2 flex flex-col gap-2">
                                {form.data.questions.map((q, i) => (
                                    <li key={i} className="flex flex-col gap-1">
                                        <div className="flex gap-2">
                                            <Input
                                                aria-label={`${t('work.listing.questions')} ${i + 1}`}
                                                value={q.question}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'questions',
                                                        form.data.questions.map((x, j) =>
                                                            j === i ? { ...x, question: e.target.value } : x,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Select
                                                aria-label={t('work.listing.type')}
                                                className="max-w-36"
                                                value={q.kind}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'questions',
                                                        form.data.questions.map((x, j) =>
                                                            j === i ? { ...x, kind: e.target.value } : x,
                                                        ),
                                                    )
                                                }
                                            >
                                                <option value="yes_no">{t('work.listing.kind.yes_no')}</option>
                                                <option value="short">{t('work.listing.kind.short')}</option>
                                            </Select>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                aria-label={t('work.delete')}
                                                onClick={() =>
                                                    form.setData(
                                                        'questions',
                                                        form.data.questions.filter((_, j) => j !== i),
                                                    )
                                                }
                                            >
                                                <X className="size-4" aria-hidden />
                                            </Button>
                                        </div>
                                        {errorFor(`questions.${i}.question`) && (
                                            <p className="text-danger-text text-sm">
                                                {errorFor(`questions.${i}.question`)}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {form.data.questions.length < 5 && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="mt-2"
                                    icon={<Plus className="size-4" aria-hidden />}
                                    onClick={() =>
                                        form.setData('questions', [
                                            ...form.data.questions,
                                            { question: '', kind: 'yes_no' },
                                        ])
                                    }
                                >
                                    {t('work.listing.add_question')}
                                </Button>
                            )}
                        </fieldset>
                        <div className="flex flex-wrap gap-2 md:col-span-2">
                            <Button type="submit" variant="secondary" loading={form.processing}>
                                {live ? t('work.save') : t('work.listing.save_draft')}
                            </Button>
                            {!live && (
                                <Button
                                    type="button"
                                    loading={form.processing}
                                    disabled={!employer.verified}
                                    onClick={() => submit(true)}
                                >
                                    {t('work.listing.publish')}
                                </Button>
                            )}
                            {listing && live && (
                                <>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(`/work/employer/listings/${listing.id}/close`, {
                                                status: 'filled',
                                            })
                                        }
                                    >
                                        {t('work.listing.filled')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(`/work/employer/listings/${listing.id}/close`, {
                                                status: 'closed',
                                            })
                                        }
                                    >
                                        {t('work.listing.close')}
                                    </Button>
                                </>
                            )}
                            {listing && ['closed', 'expired'].includes(listing.status) && (
                                <Button
                                    type="button"
                                    onClick={() =>
                                        router.post(`/work/employer/listings/${listing.id}/renew`, {
                                            closes_on: inDays(21),
                                        })
                                    }
                                >
                                    {t('work.listing.renew')}
                                </Button>
                            )}
                            <Link
                                href="/work/employer"
                                className="text-primary self-center text-sm font-semibold hover:underline"
                            >
                                {t('work.cancel')}
                            </Link>
                        </div>
                    </form>
                </Card>
                {aiEnabled && (
                    <Card className="h-fit">
                        <CardTitle>
                            <span className="flex items-center gap-2">
                                <Sparkles className="size-4" aria-hidden /> {t('work.listing.write_title')}
                            </span>
                        </CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">{t('work.listing.write_hint')}</p>
                        <Field label={t('hubops.events.write_notes')} className="mt-3">
                            <Textarea
                                rows={5}
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                maxLength={1500}
                            />
                        </Field>
                        <Button
                            type="button"
                            variant="secondary"
                            className="mt-2"
                            loading={writing}
                            disabled={notes.trim().length < 10}
                            onClick={write}
                        >
                            {t('work.listing.write_go')}
                        </Button>
                        {writeMessage && <p className="text-fg mt-2 text-sm">{writeMessage}</p>}
                        <p className="text-fg-muted mt-2 text-xs">{t('ai.notice')}</p>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
