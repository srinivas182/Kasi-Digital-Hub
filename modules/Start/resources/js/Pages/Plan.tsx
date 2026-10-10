import { Head, Link, router, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    business: { id: string; name: string };
    person: { name: string; assisted: boolean };
    plan: { sections: Record<string, string>; numbers: Record<string, number>; completed: boolean };
    sections: string[];
    ai: boolean;
}

// The platform's money format (same on every phone and browser), not the browser's locale.
const money = (rands: number) => formatMoney(Math.round(rands * 100));

/** Price and break-even, worked out on the phone with the same rules as the server (Plans::calculate). */
export function calculate(cost: number | null, markup: number | null, price: number | null, fixed: number | null) {
    const p = price ?? (cost !== null && markup !== null ? Math.round(cost * (1 + markup / 100) * 100) / 100 : null);
    const profit = p !== null && cost !== null ? Math.round((p - cost) * 100) / 100 : null;
    return {
        price: p,
        profit,
        breakEven: profit !== null && profit > 0 && fixed !== null ? Math.ceil(fixed / profit) : null,
        loss: profit !== null && profit <= 0,
    };
}

export default function Plan({ business, person, plan, sections, ai }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const [values, setValues] = useState<Record<string, string>>(plan.sections);
    const [numbers, setNumbers] = useState<Record<string, string>>(
        Object.fromEntries(
            ['cost', 'markup', 'price', 'fixed'].map((k) => [
                k,
                plan.numbers[k] !== undefined ? String(plan.numbers[k]) : '',
            ]),
        ),
    );
    const [suggestion, setSuggestion] = useState<{ section: string; text: string; warnings: string[] } | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const num = (k: string) => (numbers[k] === '' || numbers[k] === undefined ? null : Number(numbers[k]));
    const calc = calculate(num('cost'), num('markup'), num('price'), num('fixed'));

    const improve = async (section: string) => {
        setBusy(section);
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        const response = await fetch(`/start/businesses/${business.id}/plan/improve`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': match?.[1] ? decodeURIComponent(match[1]) : '',
            },
            body: JSON.stringify({ section, text: values[section] ?? '' }),
        }).catch(() => null);
        const result = (await response?.json().catch(() => null)) as {
            ok: boolean;
            text?: string;
            warnings?: string[];
        } | null;
        setBusy(null);
        if (result?.ok && result.text) setSuggestion({ section, text: result.text, warnings: result.warnings ?? [] });
    };

    const save = () =>
        router.put(
            `/start/businesses/${business.id}/plan`,
            {
                sections: values,
                numbers: Object.fromEntries(Object.entries(numbers).map(([k, v]) => [k, v === '' ? null : Number(v)])),
            },
            { preserveScroll: true },
        );

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('start.plan.title')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <Link
                href={`/start/businesses/${business.id}`}
                className="text-primary text-sm font-semibold hover:underline"
            >
                ← {business.name}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('start.plan.title')}</h1>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    {sections.map((s) => (
                        <div key={s} className="mb-6">
                            <Field label={t(`start.plan.section.${s}`)} hint={t(`start.plan.hint.${s}`)}>
                                <Textarea
                                    rows={4}
                                    maxLength={3000}
                                    value={values[s] ?? ''}
                                    onChange={(e) => setValues({ ...values, [s]: e.target.value })}
                                />
                            </Field>
                            {ai && (values[s] ?? '').trim().length >= 20 && (
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    className="mt-2"
                                    icon={<Sparkles className="size-4" aria-hidden />}
                                    loading={busy === s}
                                    onClick={() => improve(s)}
                                >
                                    {t('start.plan.improve')}
                                </Button>
                            )}
                            {suggestion?.section === s && (
                                <div className="border-primary bg-primary-soft mt-2 rounded-lg border p-3 text-sm">
                                    <p className="text-fg">{suggestion.text}</p>
                                    {suggestion.warnings.length > 0 && (
                                        <p className="text-danger-text mt-2">
                                            {t('work.ai.warning')} <strong>{suggestion.warnings.join(', ')}</strong>
                                        </p>
                                    )}
                                    <div className="mt-2 flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                setValues({ ...values, [s]: suggestion.text });
                                                setSuggestion(null);
                                            }}
                                        >
                                            {t('work.ai.use')}
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => setSuggestion(null)}>
                                            {t('work.ai.dismiss')}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                    <Button onClick={save}>{t('work.save')}</Button>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('start.calc.title')}</CardTitle>
                    <div className="mt-3 grid grid-cols-2 gap-3">
                        {(['cost', 'markup', 'price', 'fixed'] as const).map((k) => (
                            <Field key={k} label={t(`start.calc.${k}`)}>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={numbers[k] ?? ''}
                                    onChange={(e) => setNumbers({ ...numbers, [k]: e.target.value })}
                                />
                            </Field>
                        ))}
                    </div>
                    <dl className="mt-4 flex flex-col gap-1 text-sm" aria-live="polite">
                        {calc.price !== null && (
                            <div className="flex justify-between">
                                <dt className="text-fg-muted">{t('start.calc.result_price')}</dt>
                                <dd className="text-fg font-semibold">{money(calc.price)}</dd>
                            </div>
                        )}
                        {calc.profit !== null && (
                            <div className="flex justify-between">
                                <dt className="text-fg-muted">{t('start.calc.result_profit')}</dt>
                                <dd className="text-fg font-semibold">{money(calc.profit)}</dd>
                            </div>
                        )}
                        {calc.breakEven !== null && (
                            <div className="flex justify-between">
                                <dt className="text-fg-muted">{t('start.calc.result_break_even')}</dt>
                                <dd className="text-fg font-semibold">{calc.breakEven}</dd>
                            </div>
                        )}
                    </dl>
                    {calc.loss && <p className="text-danger-text mt-2 text-sm">{t('start.calc.loss')}</p>}
                    <p className="text-fg-muted mt-3 text-xs">{t('start.calc.explain')}</p>
                </Card>
            </div>
        </AppLayout>
    );
}
