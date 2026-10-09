import { Head, Link, router, usePage } from '@inertiajs/react';
import { BadgeCheck, Briefcase } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Field, SearchInput, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface JobCard {
    id: string;
    title: string;
    employer: string;
    verified: boolean;
    community: boolean;
    type: string;
    place: string | null;
    pay: string;
    noExperience: boolean;
    closesOn: string;
    distanceKm: number | null;
    saved: boolean;
}

interface Props {
    filters: {
        q?: string;
        type?: string;
        sector?: string;
        distance?: number | string;
        no_experience?: boolean | string;
        saved?: boolean | string;
    };
    jobs: JobCard[];
    hasLocation: boolean;
    options: { types: string[]; sectors: string[] };
}

const truthy = (v: unknown) => v === true || v === '1' || v === 'true';

export default function JobsIndex({ filters, jobs, hasLocation, options }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [values, setValues] = useState({
        q: filters.q ?? '',
        type: filters.type ?? '',
        sector: filters.sector ?? '',
        distance: filters.distance ? String(filters.distance) : '',
        no_experience: truthy(filters.no_experience),
        saved: truthy(filters.saved),
    });
    const apply = (next: typeof values) => {
        setValues(next);
        router.get(
            '/work/jobs',
            Object.fromEntries(
                Object.entries({
                    ...next,
                    no_experience: next.no_experience ? 1 : '',
                    saved: next.saved ? 1 : '',
                }).filter(([, v]) => v !== ''),
            ),
            { preserveState: true, preserveScroll: true },
        );
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        apply(values);
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.jobs.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('work.jobs.title')}</h1>
            <form
                role="search"
                onSubmit={submit}
                className="rounded-card border-line bg-surface mt-4 grid gap-3 border p-4 md:grid-cols-4"
            >
                <Field label={t('work.jobs.search')} className="md:col-span-2">
                    <SearchInput
                        value={values.q}
                        onChange={(e) => setValues({ ...values, q: e.target.value })}
                        aria-label={t('work.jobs.search')}
                    />
                </Field>
                <Field label={t('work.listing.type')}>
                    <Select value={values.type} onChange={(e) => apply({ ...values, type: e.target.value })}>
                        <option value="">{t('admin.all')}</option>
                        {options.types.map((v) => (
                            <option key={v} value={v}>
                                {t(`work.type.${v}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('work.employer.sector')}>
                    <Select value={values.sector} onChange={(e) => apply({ ...values, sector: e.target.value })}>
                        <option value="">{t('admin.all')}</option>
                        {options.sectors.map((v) => (
                            <option key={v} value={v}>
                                {t(`work.sector.${v}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                {hasLocation && (
                    <Field label={t('work.jobs.distance')}>
                        <Select
                            value={values.distance}
                            onChange={(e) => apply({ ...values, distance: e.target.value })}
                        >
                            <option value="">{t('work.jobs.any_distance')}</option>
                            {[10, 25, 50, 100].map((km) => (
                                <option key={km} value={km}>
                                    {t('work.jobs.within', { km })}
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}
                <div className="flex flex-wrap items-center gap-4 md:col-span-3">
                    <Checkbox
                        label={t('work.jobs.no_experience')}
                        checked={values.no_experience}
                        onCheckedChange={(c) => apply({ ...values, no_experience: c === true })}
                    />
                    <Checkbox
                        label={t('work.jobs.saved_only')}
                        checked={values.saved}
                        onCheckedChange={(c) => apply({ ...values, saved: c === true })}
                    />
                    <Button type="submit" variant="secondary">
                        {t('admin.search')}
                    </Button>
                </div>
            </form>
            {jobs.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<Briefcase className="size-8" aria-hidden />} title={t('work.jobs.none')} />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2" aria-label={t('work.jobs.title')}>
                    {jobs.map((j) => (
                        <li key={j.id}>
                            <Link href={`/work/jobs/${j.id}`} className="block h-full">
                                <Card className="hover:bg-surface-muted h-full">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge tone="primary">{t(`work.type.${j.type}`)}</Badge>
                                        {j.noExperience && <Badge tone="success">{t('work.jobs.no_experience')}</Badge>}
                                        {j.saved && <Badge>{t('work.jobs.unsave')}</Badge>}
                                    </div>
                                    <p className="text-fg mt-2 text-lg font-semibold">{j.title}</p>
                                    <p className="text-fg flex items-center gap-1 text-sm">
                                        {j.employer}{' '}
                                        {j.verified && (
                                            <BadgeCheck
                                                className="text-success-text size-4"
                                                aria-label={t('work.jobs.verified')}
                                            />
                                        )}
                                    </p>
                                    <p className="text-fg mt-1 font-semibold">{j.pay}</p>
                                    <p className="text-fg-muted text-sm">
                                        {[
                                            j.place,
                                            j.distanceKm !== null ? t('work.jobs.km_away', { km: j.distanceKm }) : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
