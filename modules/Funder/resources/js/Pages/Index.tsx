import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    programmes: { id: string; name: string; funder: string; period: string; status: string }[];
    isStaff: boolean;
    funders: { id: string; name: string }[];
    provinces: { id: number; name: string }[];
    indicators: string[];
    changes: { id: number; programme: string; indicator: string; target: number; reason: string; mine: boolean }[];
}

export default function FunderIndex({ programmes, isStaff, funders, provinces, indicators, changes }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({
        organisation_id: funders[0]?.id ?? '',
        name: '',
        summary: '',
        province_ids: [] as number[],
        starts_on: '',
        ends_on: '',
        age_min: '16',
        age_max: '35',
        targets: {} as Record<string, string>,
    });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('funder.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('funder.title')}</h1>
            <p className="text-fg-muted mt-1">{t('funder.lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.target && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.target} />
                </div>
            )}
            <ul className="mt-6 grid gap-4 md:grid-cols-2">
                {programmes.map((p) => (
                    <li key={p.id}>
                        <Link href={`/funder/programmes/${p.id}`} className="block">
                            <Card className="hover:bg-surface-muted">
                                <p className="text-fg text-lg font-semibold">{p.name}</p>
                                <p className="text-fg-muted text-sm">
                                    {p.funder} · {p.period}
                                </p>
                                <Badge className="mt-2">{t(`funder.status.${p.status}`)}</Badge>
                            </Card>
                        </Link>
                    </li>
                ))}
                {programmes.length === 0 && <li className="text-fg-muted">{t('funder.none')}</li>}
            </ul>
            {isStaff && changes.length > 0 && (
                <Card className="mt-6">
                    <CardTitle>{t('funder.changes')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2 text-sm">
                        {changes.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-fg">
                                    {c.programme}: {t(`insights.indicator.${c.indicator}`)} → {c.target} ({c.reason})
                                </span>
                                {!c.mine && (
                                    <span className="flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(`/funder/target-changes/${c.id}`, { approve: true })
                                            }
                                        >
                                            {t('connect.coordinator.approve')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(`/funder/target-changes/${c.id}`, { approve: false })
                                            }
                                        >
                                            {t('learn.review.reject')}
                                        </Button>
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
            {isStaff && (
                <Card className="mt-6">
                    <CardTitle>{t('funder.new')}</CardTitle>
                    <form
                        className="mt-3 grid gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((d) => ({
                                ...d,
                                age_min: Number(d.age_min),
                                age_max: Number(d.age_max),
                                targets: Object.fromEntries(
                                    Object.entries(d.targets)
                                        .filter(([, v]) => v !== '')
                                        .map(([k, v]) => [k, Number(v)]),
                                ),
                            }));
                            form.post('/funder/programmes');
                        }}
                    >
                        <Field label={t('funder.funder')}>
                            <Select
                                value={form.data.organisation_id}
                                onChange={(e) => form.setData('organisation_id', e.target.value)}
                            >
                                {funders.map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('funder.name')} error={form.errors.name}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label={t('learn.cohort.starts')} error={form.errors.starts_on}>
                            <Input
                                type="date"
                                value={form.data.starts_on}
                                onChange={(e) => form.setData('starts_on', e.target.value)}
                            />
                        </Field>
                        <Field label={t('learn.cohort.ends')} error={form.errors.ends_on}>
                            <Input
                                type="date"
                                value={form.data.ends_on}
                                onChange={(e) => form.setData('ends_on', e.target.value)}
                            />
                        </Field>
                        <Field label={t('partner.offer.age_min')}>
                            <Input
                                type="number"
                                value={form.data.age_min}
                                onChange={(e) => form.setData('age_min', e.target.value)}
                            />
                        </Field>
                        <Field label={t('partner.offer.age_max')}>
                            <Input
                                type="number"
                                value={form.data.age_max}
                                onChange={(e) => form.setData('age_max', e.target.value)}
                            />
                        </Field>
                        <fieldset className="md:col-span-2">
                            <legend className="text-fg text-sm font-semibold">{t('funder.scope')}</legend>
                            <div className="grid grid-cols-2 gap-x-3 sm:grid-cols-3">
                                {provinces.map((p) => (
                                    <Checkbox
                                        key={p.id}
                                        label={p.name}
                                        checked={form.data.province_ids.includes(p.id)}
                                        onCheckedChange={(c) =>
                                            form.setData(
                                                'province_ids',
                                                c === true
                                                    ? [...form.data.province_ids, p.id]
                                                    : form.data.province_ids.filter((x) => x !== p.id),
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>
                        <fieldset className="md:col-span-2">
                            <legend className="text-fg text-sm font-semibold">{t('funder.targets')}</legend>
                            <div className="mt-1 grid gap-2 sm:grid-cols-2">
                                {indicators.map((k) => (
                                    <Field key={k} label={t(`insights.indicator.${k}`)}>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={form.data.targets[k] ?? ''}
                                            onChange={(e) =>
                                                form.setData('targets', { ...form.data.targets, [k]: e.target.value })
                                            }
                                        />
                                    </Field>
                                ))}
                            </div>
                        </fieldset>
                        <div>
                            <Button
                                type="submit"
                                loading={form.processing}
                                disabled={!form.data.name || !form.data.starts_on || !form.data.ends_on}
                            >
                                {t('funder.create')}
                            </Button>
                        </div>
                    </form>
                </Card>
            )}
        </AppLayout>
    );
}
