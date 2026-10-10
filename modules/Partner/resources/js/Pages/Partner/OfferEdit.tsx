import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

type Criteria = {
    stages?: string[];
    sectors?: string[];
    forms?: string[];
    province_ids?: number[];
    hub_ids?: string[];
    turnover?: string[];
    steps?: string[];
    age_min?: number | null;
    age_max?: number | null;
    min_readiness?: number | null;
};

interface Props {
    offer: {
        id: string;
        title: string;
        type: string;
        description: string;
        capacity: number | null;
        criteria: Criteria;
        documents: string[] | null;
        status: string;
        status_reason: string | null;
        valueMin: number | null;
        valueMax: number | null;
        opensOn: string;
        closesOn: string | null;
    } | null;
    options: {
        types: string[];
        stages: string[];
        forms: string[];
        sectors: string[];
        turnover: string[];
        steps: string[];
        documents: string[];
        provinces: { id: number; name: string }[];
        hubs: { id: string; name: string }[];
    };
}

function Multi<T extends string | number>({
    legend,
    values,
    selected,
    label,
    onChange,
}: {
    legend: string;
    values: T[];
    selected: T[];
    label: (v: T) => string;
    onChange: (v: T[]) => void;
}) {
    return (
        <fieldset>
            <legend className="text-fg text-sm font-semibold">{legend}</legend>
            <div className="mt-1 grid grid-cols-2 gap-x-3">
                {values.map((v) => (
                    <Checkbox
                        key={String(v)}
                        label={label(v)}
                        checked={selected.includes(v)}
                        onCheckedChange={(c) =>
                            onChange(c === true ? [...selected, v] : selected.filter((x) => x !== v))
                        }
                    />
                ))}
            </div>
        </fieldset>
    );
}

export default function OfferEdit({ offer, options }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        title: offer?.title ?? '',
        type: offer?.type ?? 'grant',
        description: offer?.description ?? '',
        value_min: offer?.valueMin !== null && offer?.valueMin !== undefined ? String(offer.valueMin) : '',
        value_max: offer?.valueMax !== null && offer?.valueMax !== undefined ? String(offer.valueMax) : '',
        opens_on: offer?.opensOn ?? today,
        closes_on: offer?.closesOn ?? '',
        capacity: offer?.capacity ? String(offer.capacity) : '',
        criteria: (offer?.criteria ?? {}) as Criteria,
        documents: offer?.documents ?? ([] as string[]),
    });
    const c = form.data.criteria;
    const setC = (patch: Partial<Criteria>) => form.setData('criteria', { ...c, ...patch });
    const num = (v: string) => (v === '' ? null : Number(v));

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={offer?.title ?? t('partner.offer.new')} />
            <Link href="/partner" className="text-primary text-sm font-semibold hover:underline">
                ← {t('nav.partner.referrals')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{offer?.title ?? t('partner.offer.new')}</h1>
            {offer && <Badge className="mt-1">{t(`partner.offer.status.${offer.status}`)}</Badge>}
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.offer && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.offer} />
                </div>
            )}
            <form
                className="mt-6 grid gap-6 lg:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((d) => ({
                        ...d,
                        value_min: num(d.value_min),
                        value_max: num(d.value_max),
                        capacity: num(d.capacity),
                        closes_on: d.closes_on || null,
                    }));
                    if (offer) form.put(`/partner/offers/${offer.id}`);
                    else form.post('/partner/offers');
                }}
            >
                <Card>
                    <CardTitle>{t('partner.offer.details')}</CardTitle>
                    <div className="mt-3 flex flex-col gap-3">
                        <Field label={t('partner.offer.f_title')} error={form.errors.title} required>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>
                        <Field label={t('partner.offer.f_type')}>
                            <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                {options.types.map((v) => (
                                    <option key={v} value={v}>
                                        {t(`partner.type.${v}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field
                            label={t('partner.offer.f_description')}
                            hint={t('partner.offer.no_fees')}
                            error={form.errors.description}
                            required
                        >
                            <Textarea
                                rows={6}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('partner.offer.f_value_min')} error={form.errors.value_min}>
                                <Input
                                    type="number"
                                    min={0}
                                    value={form.data.value_min}
                                    onChange={(e) => form.setData('value_min', e.target.value)}
                                />
                            </Field>
                            <Field label={t('partner.offer.f_value_max')} error={form.errors.value_max}>
                                <Input
                                    type="number"
                                    min={0}
                                    value={form.data.value_max}
                                    onChange={(e) => form.setData('value_max', e.target.value)}
                                />
                            </Field>
                            <Field label={t('partner.offer.f_opens')} error={form.errors.opens_on}>
                                <Input
                                    type="date"
                                    value={form.data.opens_on}
                                    onChange={(e) => form.setData('opens_on', e.target.value)}
                                />
                            </Field>
                            <Field label={t('partner.offer.f_closes')} error={form.errors.closes_on}>
                                <Input
                                    type="date"
                                    value={form.data.closes_on}
                                    onChange={(e) => form.setData('closes_on', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Multi
                            legend={t('partner.offer.f_documents')}
                            values={options.documents}
                            selected={form.data.documents}
                            label={(v) => t(`documents.type.${v}`)}
                            onChange={(v) => form.setData('documents', v)}
                        />
                    </div>
                </Card>
                <Card>
                    <CardTitle>{t('partner.offer.criteria')}</CardTitle>
                    <p className="text-fg-muted text-sm">{t('partner.offer.criteria_hint')}</p>
                    <div className="mt-3 flex flex-col gap-4">
                        <Multi
                            legend={t('start.form.stage')}
                            values={options.stages}
                            selected={c.stages ?? []}
                            label={(v) => t(`start.stage.${v}`)}
                            onChange={(v) => setC({ stages: v })}
                        />
                        <Multi
                            legend={t('partner.offer.forms')}
                            values={options.forms}
                            selected={c.forms ?? []}
                            label={(v) => t(`start.form_name.${v}`)}
                            onChange={(v) => setC({ forms: v })}
                        />
                        <Multi
                            legend={t('start.form.sector')}
                            values={options.sectors}
                            selected={c.sectors ?? []}
                            label={(v) => t(`start.sector.${v}`)}
                            onChange={(v) => setC({ sectors: v })}
                        />
                        <Multi
                            legend={t('partner.offer.steps')}
                            values={options.steps}
                            selected={c.steps ?? []}
                            label={(v) => t(`start.next.${v}`)}
                            onChange={(v) => setC({ steps: v })}
                        />
                        <Multi
                            legend={t('partner.offer.provinces')}
                            values={options.provinces.map((p) => p.id)}
                            selected={c.province_ids ?? []}
                            label={(v) => options.provinces.find((p) => p.id === v)?.name ?? ''}
                            onChange={(v) => setC({ province_ids: v })}
                        />
                        <div className="grid grid-cols-3 gap-3">
                            <Field label={t('partner.offer.age_min')}>
                                <Input
                                    type="number"
                                    min={18}
                                    value={c.age_min ?? ''}
                                    onChange={(e) => setC({ age_min: num(e.target.value) })}
                                />
                            </Field>
                            <Field label={t('partner.offer.age_max')}>
                                <Input
                                    type="number"
                                    min={18}
                                    value={c.age_max ?? ''}
                                    onChange={(e) => setC({ age_max: num(e.target.value) })}
                                />
                            </Field>
                            <Field label={t('partner.offer.min_readiness')}>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={c.min_readiness ?? ''}
                                    onChange={(e) => setC({ min_readiness: num(e.target.value) })}
                                />
                            </Field>
                        </div>
                    </div>
                </Card>
                <div className="flex flex-wrap gap-2 lg:col-span-2">
                    <Button type="submit" loading={form.processing}>
                        {t('work.save')}
                    </Button>
                    {offer && ['draft', 'rejected'].includes(offer.status) && (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => router.post(`/partner/offers/${offer.id}/submit`)}
                        >
                            {t('partner.offer.submit')}
                        </Button>
                    )}
                    {offer && offer.status === 'open' && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => router.post(`/partner/offers/${offer.id}/close`)}
                        >
                            {t('partner.offer.close')}
                        </Button>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}
