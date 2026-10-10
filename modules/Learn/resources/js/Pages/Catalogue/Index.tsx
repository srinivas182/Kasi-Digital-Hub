import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, BookOpen } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Field, SearchInput, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { formatBytes } from '../../components/format';

export interface CourseCard {
    slug: string;
    title: string;
    summary: string | null;
    provider: string;
    verified: boolean;
    accredited: boolean;
    topic: string;
    level: string;
    hours: number | null;
    language: string;
    delivery: string;
    dataBytes: number;
    saved: boolean;
}

interface Props {
    filters: Record<string, string | boolean | undefined>;
    courses: CourseCard[];
    suggested: CourseCard[];
    options: { topics: string[]; levels: string[]; delivery: string[]; languages: string[] };
}

function Card_({ c }: { c: CourseCard }) {
    const { t } = useTranslation();
    return (
        <Link href={`/learn/courses/${c.slug}`} className="block h-full">
            <Card className="hover:bg-surface-muted h-full">
                <div className="flex flex-wrap gap-1">
                    <Badge tone="primary">{t(`learn.topic.${c.topic}`)}</Badge>
                    {c.accredited && <Badge tone="success">{t('learn.catalogue.accredited')}</Badge>}
                    {c.saved && <Badge>{t('learn.catalogue.saved_badge')}</Badge>}
                </div>
                <p className="text-fg mt-2 text-lg font-semibold">{c.title}</p>
                <p className="text-fg flex items-center gap-1 text-sm">
                    {c.provider}{' '}
                    {c.verified && (
                        <BadgeCheck className="text-success-text size-4" aria-label={t('learn.catalogue.verified')} />
                    )}
                </p>
                {c.summary && <p className="text-fg-muted mt-1 line-clamp-2 text-sm">{c.summary}</p>}
                <p className="text-fg-muted mt-2 text-xs">
                    {[
                        t(`learn.level.${c.level}`),
                        c.hours ? t('learn.catalogue.hours', { hours: c.hours }) : null,
                        c.language,
                        formatBytes(c.dataBytes),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </Card>
        </Link>
    );
}

export default function CatalogueIndex({ filters, courses, suggested, options }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const truthy = (v: unknown) => v === true || v === '1' || v === 'true';
    const [values, setValues] = useState({
        q: String(filters.q ?? ''),
        topic: String(filters.topic ?? ''),
        level: String(filters.level ?? ''),
        language: String(filters.language ?? ''),
        delivery: String(filters.delivery ?? ''),
        small: truthy(filters.small),
        accredited: truthy(filters.accredited),
        saved: truthy(filters.saved),
    });
    const apply = (next: typeof values) => {
        setValues(next);
        router.get(
            '/learn/courses',
            Object.fromEntries(
                Object.entries({
                    ...next,
                    small: next.small ? 1 : '',
                    accredited: next.accredited ? 1 : '',
                    saved: next.saved ? 1 : '',
                }).filter(([, v]) => v !== ''),
            ),
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.catalogue.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('learn.catalogue.title')}</h1>
            {suggested.length > 0 && (
                <section className="mt-4" aria-labelledby="suggested">
                    <h2 id="suggested" className="text-fg text-lg font-bold">
                        {t('learn.catalogue.for_you')}
                    </h2>
                    <ul className="mt-2 grid gap-4 md:grid-cols-3">
                        {suggested.map((c) => (
                            <li key={c.slug}>
                                <Card_ c={c} />
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            <form
                role="search"
                onSubmit={(e: FormEvent) => {
                    e.preventDefault();
                    apply(values);
                }}
                className="rounded-card border-line bg-surface mt-6 grid gap-3 border p-4 md:grid-cols-4"
            >
                <Field label={t('learn.catalogue.search')} className="md:col-span-2">
                    <SearchInput
                        value={values.q}
                        onChange={(e) => setValues({ ...values, q: e.target.value })}
                        aria-label={t('learn.catalogue.search')}
                    />
                </Field>
                {(
                    [
                        ['topic', options.topics, 'learn.topic.'],
                        ['level', options.levels, 'learn.level.'],
                        ['delivery', options.delivery, 'learn.delivery.'],
                    ] as const
                ).map(([key, list, prefix]) => (
                    <Field key={key} label={t(`learn.author.${key}`)}>
                        <Select value={values[key]} onChange={(e) => apply({ ...values, [key]: e.target.value })}>
                            <option value="">{t('admin.all')}</option>
                            {list.map((v) => (
                                <option key={v} value={v}>
                                    {t(`${prefix}${v}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                ))}
                <Field label={t('learn.author.language')}>
                    <Select value={values.language} onChange={(e) => apply({ ...values, language: e.target.value })}>
                        <option value="">{t('admin.all')}</option>
                        {options.languages.map((l) => (
                            <option key={l} value={l}>
                                {l}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex flex-wrap items-center gap-4 md:col-span-4">
                    <Checkbox
                        label={t('learn.catalogue.small')}
                        checked={values.small}
                        onCheckedChange={(c) => apply({ ...values, small: c === true })}
                    />
                    <Checkbox
                        label={t('learn.catalogue.accredited')}
                        checked={values.accredited}
                        onCheckedChange={(c) => apply({ ...values, accredited: c === true })}
                    />
                    {auth.user && (
                        <Checkbox
                            label={t('learn.catalogue.saved_only')}
                            checked={values.saved}
                            onCheckedChange={(c) => apply({ ...values, saved: c === true })}
                        />
                    )}
                    <Button type="submit" variant="secondary">
                        {t('admin.search')}
                    </Button>
                </div>
            </form>
            {courses.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<BookOpen className="size-8" aria-hidden />} title={t('learn.catalogue.none')} />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3" aria-label={t('learn.catalogue.title')}>
                    {courses.map((c) => (
                        <li key={c.slug}>
                            <Card_ c={c} />
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
