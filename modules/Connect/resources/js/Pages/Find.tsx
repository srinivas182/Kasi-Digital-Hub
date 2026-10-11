import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, ProgressBar } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Suggestion {
    id: string;
    name: string;
    headline: string;
    bio: string | null;
    formats: string[];
    languages: string[];
    score: number;
    reasons: { key: string; params: Record<string, string> }[];
}

export default function Find({
    wish,
    minor,
    suggestions,
}: {
    wish: { goal: string; interests: string; languages: string[]; format: string | null };
    minor: boolean;
    suggestions: Suggestion[];
}) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const [values, setValues] = useState({
        goal: wish.goal,
        interests: wish.interests,
        languages: wish.languages.join(','),
        format: wish.format ?? '',
    });
    const [asking, setAsking] = useState<string | null>(null);
    const ask = useForm({ goal: wish.goal, message: '' });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('nav.connect.find')} />
            <h1 className="text-fg text-2xl font-bold">{t('nav.connect.find')}</h1>
            {minor && (
                <div className="mt-3">
                    <Alert tone="info" title={t('connect.minor.info')} />
                </div>
            )}
            <form
                className="rounded-card border-line bg-surface mt-4 grid gap-3 border p-4 md:grid-cols-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/connect/find', values, { preserveState: true });
                }}
            >
                <Field label={t('connect.find.goal')}>
                    <Select value={values.goal} onChange={(e) => setValues({ ...values, goal: e.target.value })}>
                        {['job', 'business', 'career', 'confidence'].map((g) => (
                            <option key={g} value={g}>
                                {t(`connect.goal.${g}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('connect.find.interests')} className="md:col-span-2">
                    <Input
                        value={values.interests}
                        onChange={(e) => setValues({ ...values, interests: e.target.value })}
                        placeholder={t('connect.find.interests_hint')}
                    />
                </Field>
                <Field label={t('connect.find.format')}>
                    <Select value={values.format} onChange={(e) => setValues({ ...values, format: e.target.value })}>
                        <option value="">{t('admin.all')}</option>
                        {(minor ? ['hub'] : ['phone', 'video', 'hub']).map((f) => (
                            <option key={f} value={f}>
                                {t(`connect.format.${f}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="md:col-span-4">
                    <Button type="submit" variant="secondary">
                        {t('connect.find.search')}
                    </Button>
                </div>
            </form>
            {errors?.request && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.request} />
                </div>
            )}
            <ul className="mt-6 grid gap-4 md:grid-cols-2">
                {suggestions.map((s) => (
                    <li key={s.id}>
                        <Card className="h-full">
                            <p className="text-fg text-lg font-semibold">{s.name}</p>
                            <p className="text-fg">{s.headline}</p>
                            {s.bio && <p className="text-fg-muted mt-1 line-clamp-3 text-sm">{s.bio}</p>}
                            <div className="mt-2 flex flex-wrap gap-1">
                                {s.formats.map((f) => (
                                    <Badge key={f}>{t(`connect.format.${f}`)}</Badge>
                                ))}
                                <Badge tone="neutral">{s.languages.join(', ')}</Badge>
                            </div>
                            <div className="mt-3">
                                <ProgressBar value={s.score} label={t('connect.find.fit', { score: s.score })} />
                            </div>
                            <ul className="text-fg mt-2 list-disc pl-5 text-sm">
                                {s.reasons.map((r, i) => (
                                    <li key={i}>
                                        {t(`connect.reason.${r.key}`, {
                                            ...r.params,
                                            goal: r.params.goal ? t(`connect.goal.${r.params.goal}`) : '',
                                            format: r.params.format ? t(`connect.format.${r.params.format}`) : '',
                                        })}
                                    </li>
                                ))}
                            </ul>
                            {asking === s.id ? (
                                <form
                                    className="mt-3 flex flex-col gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        ask.post(`/connect/mentors/${s.id}/request`);
                                    }}
                                >
                                    <Field label={t('connect.find.message')}>
                                        <Textarea
                                            rows={3}
                                            maxLength={1000}
                                            value={ask.data.message}
                                            onChange={(e) => ask.setData('message', e.target.value)}
                                        />
                                    </Field>
                                    <div>
                                        <Button type="submit" size="sm" loading={ask.processing}>
                                            {t('connect.find.send')}
                                        </Button>
                                    </div>
                                </form>
                            ) : (
                                <Button size="sm" className="mt-3" onClick={() => setAsking(s.id)}>
                                    {t('connect.find.ask')}
                                </Button>
                            )}
                        </Card>
                    </li>
                ))}
                {suggestions.length === 0 && <li className="text-fg-muted">{t('connect.find.none')}</li>}
            </ul>
        </AppLayout>
    );
}
