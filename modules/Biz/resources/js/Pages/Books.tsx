import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface EntryRow {
    id: string;
    direction: 'in' | 'out';
    cents: number;
    category: string;
    method: string;
    on: string;
    note: string | null;
    source: string;
    receipt: boolean;
    canChange: boolean;
}

interface Props {
    business: { id: string; name: string };
    lowStock: string[];
    nextDeadline: { rule: string; due_on: string; overdue: boolean } | null;
    businesses: { id: string; name: string }[];
    month: string;
    summary: {
        month: string;
        in: number;
        out: number;
        profit: number;
        cash: Record<string, number>;
        top_costs: { category: string; cents: number }[];
        previous_profit: number;
    };
    entries: EntryRow[];
    opening: Record<string, number>;
    categories: { in: string[]; out: string[] };
    methods: string[];
    notTrading: string[];
    contacts: { id: number; kind: string; name: string }[];
}

const shift = (month: string, by: number) => {
    const [y = 2026, m = 1] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + by, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

/** KasiBiz books (S28): two-tap money in / out, the month at a glance, the cash book. */
export default function Books({
    business,
    lowStock = [],
    nextDeadline = null,
    businesses,
    month,
    summary: s,
    entries,
    opening,
    categories,
    methods,
    contacts,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [direction, setDirection] = useState<'in' | 'out' | null>(null);
    const form = useForm<{
        amount: string;
        category: string;
        method: string;
        occurred_on: string;
        note: string;
        contact_id: string;
        receipt: File | null;
    }>({
        amount: '',
        category: '',
        method: 'cash',
        occurred_on: '',
        note: '',
        contact_id: '',
        receipt: null,
    });
    const openingForm = useForm({
        cash: String((opening.cash ?? 0) / 100),
        card: String((opening.card ?? 0) / 100),
        mobile: String((opening.mobile ?? 0) / 100),
    });
    const [showOpening, setShowOpening] = useState(false);
    const go = (params: Record<string, string>) =>
        router.get('/biz', { business: business.id, month, ...params }, { preserveScroll: true });
    const save = () => {
        form.transform((d) => ({
            business: business.id,
            direction,
            amount_cents: Math.round(Number(d.amount) * 100),
            category: d.category,
            method: d.method,
            occurred_on: d.occurred_on || null,
            note: d.note || null,
            contact_id: d.contact_id ? Number(d.contact_id) : null,
            receipt: d.receipt,
        }));
        form.post('/biz/entries', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDirection(null);
            },
        });
    };
    const diff = s.profit - s.previous_profit;
    const biggest = Math.max(1, ...s.top_costs.map((c) => c.cents));
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('biz.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{business.name}</h1>
                {businesses.length > 1 && (
                    <Select
                        aria-label={t('biz.choose_business')}
                        value={business.id}
                        onChange={(e) => router.get('/biz', { business: e.target.value })}
                    >
                        {businesses.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </Select>
                )}
            </div>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <Link href="/biz/stock" className="border-line bg-surface hover:bg-surface-muted rounded-lg border p-3">
                    <span className="text-fg-muted block text-sm">{t('biz.home.low_stock')}</span>
                    <span className="text-fg font-semibold">
                        {lowStock.length > 0 ? lowStock.slice(0, 3).join(', ') : t('biz.home.stock_ok')}
                    </span>
                </Link>
                <Link
                    href="/biz/compliance"
                    className="border-line bg-surface hover:bg-surface-muted rounded-lg border p-3"
                >
                    <span className="text-fg-muted block text-sm">{t('biz.home.next_deadline')}</span>
                    <span className={`font-semibold ${nextDeadline?.overdue ? 'text-danger-text' : 'text-fg'}`}>
                        {nextDeadline
                            ? `${t(`biz.compliance.rule.${nextDeadline.rule}`)} · ${formatDate(nextDeadline.due_on)}`
                            : t('biz.home.no_deadline')}
                    </span>
                </Link>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.biz && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.biz} />
                </div>
            )}
            <div className="mt-4 grid grid-cols-2 gap-3">
                <Button
                    size="lg"
                    variant={direction === 'in' ? 'primary' : 'secondary'}
                    onClick={() => {
                        setDirection('in');
                        form.setData('category', categories.in[0] ?? '');
                    }}
                >
                    <ArrowDownLeft className="size-5" aria-hidden /> {t('biz.money_in')}
                </Button>
                <Button
                    size="lg"
                    variant={direction === 'out' ? 'primary' : 'secondary'}
                    onClick={() => {
                        setDirection('out');
                        form.setData('category', categories.out[0] ?? '');
                    }}
                >
                    <ArrowUpRight className="size-5" aria-hidden /> {t('biz.money_out')}
                </Button>
            </div>
            {direction && (
                <Card className="mt-3">
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            save();
                        }}
                    >
                        <Field
                            label={t('biz.amount')}
                            error={(form.errors as Record<string, string | undefined>).amount_cents}
                        >
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                inputMode="decimal"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                            />
                        </Field>
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('biz.what_for')}</legend>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {categories[direction].map((c) => (
                                    <label
                                        key={c}
                                        className={`cursor-pointer rounded-full border px-3 py-1 text-sm ${form.data.category === c ? 'bg-primary text-primary-fg border-primary' : 'border-line text-fg'}`}
                                    >
                                        <input
                                            type="radio"
                                            className="sr-only"
                                            name="category"
                                            checked={form.data.category === c}
                                            onChange={() => form.setData('category', c)}
                                        />
                                        {t(`biz.category.${c}`)}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend className="text-fg text-sm font-semibold">{t('biz.how_paid')}</legend>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {methods.map((m) => (
                                    <label
                                        key={m}
                                        className={`cursor-pointer rounded-full border px-3 py-1 text-sm ${form.data.method === m ? 'bg-primary text-primary-fg border-primary' : 'border-line text-fg'}`}
                                    >
                                        <input
                                            type="radio"
                                            className="sr-only"
                                            name="method"
                                            checked={form.data.method === m}
                                            onChange={() => form.setData('method', m)}
                                        />
                                        {t(`biz.method.${m}`)}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('biz.date')} hint={t('biz.date_hint')}>
                                <Input
                                    type="date"
                                    value={form.data.occurred_on}
                                    onChange={(e) => form.setData('occurred_on', e.target.value)}
                                />
                            </Field>
                            <Field label={t('biz.note')}>
                                <Input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                            </Field>
                        </div>
                        {contacts.length > 0 && (
                            <Field label={direction === 'in' ? t('biz.customer') : t('biz.supplier')}>
                                <Select
                                    value={form.data.contact_id}
                                    onChange={(e) => form.setData('contact_id', e.target.value)}
                                >
                                    <option value="">{t('biz.nobody')}</option>
                                    {contacts.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        )}
                        {direction === 'out' && (
                            <Field label={t('biz.receipt')} hint={t('biz.receipt_hint')}>
                                <FileInput
                                    file={form.data.receipt}
                                    onChange={(f) => form.setData('receipt', f)}
                                    chooseLabel={t('documents.file')}
                                    photoLabel={t('documents.take_photo')}
                                    accept="image/*"
                                />
                            </Field>
                        )}
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                loading={form.processing}
                                disabled={!form.data.amount || Number(form.data.amount) <= 0}
                            >
                                {t('biz.save')}
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setDirection(null)}>
                                {t('biz.cancel')}
                            </Button>
                        </div>
                    </form>
                </Card>
            )}
            <div className="mt-6 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-fg text-xl font-bold">
                    {new Date(`${month}-01`).toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' })}
                </h2>
                <span className="flex gap-2">
                    <Button size="sm" variant="ghost" onClick={() => go({ month: shift(month, -1) })}>
                        ← {t('biz.prev_month')}
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => go({ month: shift(month, 1) })}>
                        {t('biz.next_month')} →
                    </Button>
                </span>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Card>
                    <p className="text-fg-muted text-sm">{t('biz.summary.in')}</p>
                    <p className="text-fg text-xl font-bold">{formatMoney(s.in)}</p>
                </Card>
                <Card>
                    <p className="text-fg-muted text-sm">{t('biz.summary.out')}</p>
                    <p className="text-fg text-xl font-bold">{formatMoney(s.out)}</p>
                </Card>
                <Card>
                    <p className="text-fg-muted text-sm">{t('biz.summary.profit')}</p>
                    <p className={`text-xl font-bold ${s.profit < 0 ? 'text-danger-text' : 'text-fg'}`}>
                        {formatMoney(s.profit)}
                    </p>
                    <p className="text-fg-muted text-xs">
                        {diff === 0
                            ? t('biz.summary.same')
                            : diff > 0
                              ? t('biz.summary.more', { amount: formatMoney(diff) })
                              : t('biz.summary.less', { amount: formatMoney(-diff) })}
                    </p>
                </Card>
                <Card>
                    <p className="text-fg-muted text-sm">{t('biz.summary.cash_on_hand')}</p>
                    {methods.map((m) => (
                        <p key={m} className="text-fg text-sm">
                            {t(`biz.method.${m}`)}: {formatMoney(s.cash[m] ?? 0)}
                        </p>
                    ))}
                </Card>
            </div>
            <p className="text-fg-muted mt-2 text-xs">{t('biz.summary.profit_note')}</p>
            {s.top_costs.length > 0 && (
                <Card className="mt-3">
                    <CardTitle>{t('biz.summary.biggest_costs')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2">
                        {s.top_costs.map((c) => (
                            <li key={c.category} className="text-sm">
                                <span className="text-fg flex justify-between">
                                    <span>{t(`biz.category.${c.category}`)}</span>
                                    <span>{formatMoney(c.cents)}</span>
                                </span>
                                <span className="bg-surface-muted mt-1 block h-2 rounded" aria-hidden>
                                    <span
                                        className="bg-primary block h-2 rounded"
                                        style={{ width: `${Math.round((100 * c.cents) / biggest)}%` }}
                                    />
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
            <Card className="mt-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle>{t('biz.cash_book')}</CardTitle>
                    <span className="flex flex-wrap gap-3 text-sm">
                        <a
                            href={`/biz/export.csv?business=${business.id}&month=${month}`}
                            className="text-primary font-semibold hover:underline"
                        >
                            {t('biz.export_csv')}
                        </a>
                        <a
                            href={`/biz/summary.pdf?business=${business.id}&month=${month}`}
                            className="text-primary font-semibold hover:underline"
                        >
                            {t('biz.export_pdf')}
                        </a>
                        <Link href="/biz/owed" className="text-primary font-semibold hover:underline">
                            {t('biz.owed.title')}
                        </Link>
                    </span>
                </div>
                <ul className="mt-2 flex flex-col">
                    {entries.map((e) => (
                        <li
                            key={e.id}
                            className="border-line flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm"
                        >
                            <span>
                                <span className="text-fg font-semibold">{t(`biz.category.${e.category}`)}</span>
                                <span className="text-fg-muted block">
                                    {formatDate(e.on)} · {t(`biz.method.${e.method}`)}
                                    {e.note ? ` · ${e.note}` : ''}
                                </span>
                                {e.source !== 'manual' && <Badge tone="neutral">{t(`biz.source.${e.source}`)}</Badge>}
                            </span>
                            <span className="flex items-center gap-2">
                                <span
                                    className={
                                        e.direction === 'in'
                                            ? 'text-success-text font-semibold'
                                            : 'text-fg font-semibold'
                                    }
                                >
                                    {e.direction === 'in' ? '+' : '−'}
                                    {formatMoney(e.cents)}
                                </span>
                                {e.canChange && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={t('biz.remove_entry')}
                                        onClick={() =>
                                            router.delete(`/biz/entries/${e.id}`, {
                                                data: { business: business.id },
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        {t('biz.remove')}
                                    </Button>
                                )}
                                {e.source === 'market' && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(
                                                `/biz/entries/${e.id}/hide`,
                                                { business: business.id, hidden: true },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('biz.hide')}
                                    </Button>
                                )}
                            </span>
                        </li>
                    ))}
                    {entries.length === 0 && <li className="text-fg-muted py-2 text-sm">{t('biz.no_entries')}</li>}
                </ul>
                <button
                    type="button"
                    className="text-fg-muted mt-3 text-sm underline"
                    onClick={() => setShowOpening(!showOpening)}
                >
                    {t('biz.opening.link')}
                </button>
                {showOpening && (
                    <form
                        className="mt-2 grid gap-2 sm:grid-cols-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            openingForm.transform((d) => ({
                                business: business.id,
                                cash: Math.round(Number(d.cash) * 100),
                                card: Math.round(Number(d.card) * 100),
                                mobile: Math.round(Number(d.mobile) * 100),
                            }));
                            openingForm.post('/biz/opening', { preserveScroll: true });
                        }}
                    >
                        {(['cash', 'card', 'mobile'] as const).map((m) => (
                            <Field key={m} label={t(`biz.method.${m}`)}>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={openingForm.data[m]}
                                    onChange={(e) => openingForm.setData(m, e.target.value)}
                                />
                            </Field>
                        ))}
                        <div className="flex items-end">
                            <Button type="submit" size="sm" variant="secondary">
                                {t('biz.opening.save')}
                            </Button>
                        </div>
                    </form>
                )}
            </Card>
        </AppLayout>
    );
}
