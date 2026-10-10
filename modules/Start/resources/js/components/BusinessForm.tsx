import { useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Field, Input, Select } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

export interface BusinessData {
    name: string;
    sells: string | null;
    sector: string;
    stage: string;
    place_name: string | null;
    people: number;
    turnover_band: string | null;
    customers: string | null;
    municipalityId?: number | null;
}

/** Business profile form (create and edit). */
export function BusinessForm({
    business,
    action,
    method,
    options,
    cities,
    submitLabel,
}: {
    business?: BusinessData;
    action: string;
    method: 'post' | 'put';
    options: { sectors: string[]; stages: string[]; turnover: string[] };
    cities: { id: number; name: string }[];
    submitLabel: string;
}) {
    const { t } = useTranslation();
    const form = useForm({
        name: business?.name ?? '',
        sells: business?.sells ?? '',
        sector: business?.sector ?? 'retail',
        stage: business?.stage ?? 'idea',
        municipality_id: business?.municipalityId ? String(business.municipalityId) : '',
        place_name: business?.place_name ?? '',
        people: String(business?.people ?? 1),
        turnover_band: business?.turnover_band ?? '',
        customers: business?.customers ?? '',
    });

    return (
        <form
            className="grid gap-4 md:grid-cols-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({
                    ...d,
                    people: Number(d.people),
                    municipality_id: d.municipality_id || null,
                    turnover_band: d.turnover_band || null,
                }));
                if (method === 'post') form.post(action);
                else form.put(action, { preserveScroll: true });
            }}
        >
            <Field label={t('start.form.name')} error={form.errors.name} required>
                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            </Field>
            <Field label={t('start.form.sells')} error={form.errors.sells}>
                <Input value={form.data.sells} onChange={(e) => form.setData('sells', e.target.value)} />
            </Field>
            <Field label={t('start.form.sector')} error={form.errors.sector}>
                <Select value={form.data.sector} onChange={(e) => form.setData('sector', e.target.value)}>
                    {options.sectors.map((s) => (
                        <option key={s} value={s}>
                            {t(`start.sector.${s}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('start.form.stage')} error={form.errors.stage}>
                <Select value={form.data.stage} onChange={(e) => form.setData('stage', e.target.value)}>
                    {options.stages.map((s) => (
                        <option key={s} value={s}>
                            {t(`start.stage.${s}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('start.form.city')} error={form.errors.municipality_id}>
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
            <Field label={t('start.form.place')} error={form.errors.place_name}>
                <Input value={form.data.place_name} onChange={(e) => form.setData('place_name', e.target.value)} />
            </Field>
            <Field label={t('start.form.people')} error={form.errors.people}>
                <Input
                    type="number"
                    min={1}
                    value={form.data.people}
                    onChange={(e) => form.setData('people', e.target.value)}
                />
            </Field>
            <Field
                label={t('start.form.turnover')}
                hint={t('start.form.turnover_hint')}
                error={form.errors.turnover_band}
            >
                <Select value={form.data.turnover_band} onChange={(e) => form.setData('turnover_band', e.target.value)}>
                    <option value="">-</option>
                    {options.turnover.map((v) => (
                        <option key={v} value={v}>
                            {t(`start.turnover.${v}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('start.form.customers')} error={form.errors.customers} className="md:col-span-2">
                <Input value={form.data.customers} onChange={(e) => form.setData('customers', e.target.value)} />
            </Field>
            <div>
                <Button type="submit" loading={form.processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
