import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Item {
    id: number;
    name: string;
    unit: string;
    quantity: number;
    reorder_level: number;
    cost_cents: number;
    value_cents: number;
    low: boolean;
    linked: boolean;
}

interface Props {
    business: { id: string; name: string };
    items: Item[];
    value_cents: number;
    units: string[];
    products: { id: string; title: string; stock: number }[];
    history: {
        item: number;
        moves: { kind: string; change: number; after: number; note: string | null; at: string }[];
    } | null;
}

function MoveForm({ item }: { item: Item }) {
    const { t } = useTranslation();
    const form = useForm({ kind: 'bought', quantity: '', cost: '', to_books: true });
    return (
        <form
            className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({
                    kind: d.kind,
                    quantity: Number(d.quantity || 0),
                    cost_cents: d.kind === 'bought' && d.cost ? Math.round(Number(d.cost) * 100) : null,
                    to_books: d.kind === 'bought' && d.to_books,
                }));
                form.post(`/biz/stock/${item.id}/move`, {
                    preserveScroll: true,
                    onSuccess: () => form.reset('quantity', 'cost'),
                });
            }}
        >
            <Field label={t('biz.stock.what_happened')}>
                <Select value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                    {['bought', 'sold', 'used', 'damaged', 'lost', 'counted'].map((k) => (
                        <option key={k} value={k}>
                            {t(`biz.stock.kind.${k}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={form.data.kind === 'counted' ? t('biz.stock.counted_qty') : t('biz.stock.how_many')}>
                <Input
                    type="number"
                    min={0}
                    inputMode="numeric"
                    value={form.data.quantity}
                    onChange={(e) => form.setData('quantity', e.target.value)}
                />
            </Field>
            {form.data.kind === 'bought' && (
                <>
                    <Field label={t('biz.stock.paid')}>
                        <Input
                            type="number"
                            min={0}
                            step="0.01"
                            inputMode="decimal"
                            value={form.data.cost}
                            onChange={(e) => form.setData('cost', e.target.value)}
                        />
                    </Field>
                    <Checkbox
                        label={t('biz.stock.to_books')}
                        checked={form.data.to_books}
                        onCheckedChange={(c) => form.setData('to_books', c === true)}
                    />
                </>
            )}
            <div className="col-span-full">
                <Button type="submit" size="sm" disabled={form.data.quantity === ''} loading={form.processing}>
                    {t('biz.stock.save_move')}
                </Button>
            </div>
        </form>
    );
}

/** Stock (S29): low items first; quick bought / sold / count; history; link to KasiMarket. */
export default function StockPage({ business, items, value_cents, units, products, history }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [open, setOpen] = useState<number | null>(null);
    const add = useForm({ name: '', unit: 'each', cost: '', quantity: '', reorder_level: '', market_product_id: '' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('biz.stock.title')} />
            <Link href="/biz" className="text-primary text-sm font-semibold hover:underline">
                ← {business.name}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <h1 className="text-fg text-2xl font-bold">{t('biz.stock.title')}</h1>
                <p className="text-fg">{t('biz.stock.value', { value: formatMoney(value_cents) })}</p>
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
            <ul className="mt-4 flex flex-col gap-3">
                {items.map((i) => (
                    <li key={i.id}>
                        <Card>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-fg font-semibold">
                                    {i.name} {i.linked && <Badge tone="neutral">{t('biz.stock.linked')}</Badge>}
                                </span>
                                <span className="flex items-center gap-2">
                                    {i.low && <Badge tone="warning">{t('biz.stock.low')}</Badge>}
                                    <span className="text-fg">
                                        {i.quantity} {t(`biz.stock.unit.${i.unit}`)}
                                    </span>
                                </span>
                            </div>
                            <p className="text-fg-muted text-sm">
                                {t('biz.stock.reorder_at', { n: i.reorder_level })} · {formatMoney(i.value_cents)}
                            </p>
                            <div className="mt-1 flex gap-3 text-sm">
                                <button
                                    type="button"
                                    className="text-primary font-semibold underline"
                                    onClick={() => setOpen(open === i.id ? null : i.id)}
                                >
                                    {t('biz.stock.record_move')}
                                </button>
                                <Link
                                    href={`/biz/stock?item=${i.id}`}
                                    preserveScroll
                                    className="text-primary underline"
                                >
                                    {t('biz.stock.history')}
                                </Link>
                            </div>
                            {open === i.id && <MoveForm item={i} />}
                            {history?.item === i.id && (
                                <ul className="text-fg-muted mt-2 text-sm">
                                    {history.moves.map((m, n) => (
                                        <li key={n}>
                                            {formatDateTime(m.at)} · {t(`biz.stock.kind.${m.kind}`)}{' '}
                                            {m.change > 0 ? `+${m.change}` : m.change} → {m.after}
                                            {m.note ? ` · ${m.note}` : ''}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </li>
                ))}
                {items.length === 0 && <li className="text-fg-muted">{t('biz.stock.none')}</li>}
            </ul>
            <Card className="mt-6">
                <CardTitle>{t('biz.stock.add')}</CardTitle>
                <form
                    className="mt-2 grid gap-3 sm:grid-cols-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        add.transform((d) => ({
                            name: d.name,
                            unit: d.unit,
                            cost_cents: d.cost ? Math.round(Number(d.cost) * 100) : 0,
                            quantity: Number(d.quantity || 0),
                            reorder_level: Number(d.reorder_level || 0),
                            market_product_id: d.market_product_id || null,
                        }));
                        add.post('/biz/stock', { preserveScroll: true, onSuccess: () => add.reset() });
                    }}
                >
                    {products.length > 0 && (
                        <Field label={t('biz.stock.link_product')} className="sm:col-span-3">
                            <Select
                                value={add.data.market_product_id}
                                onChange={(e) => {
                                    const p = products.find((x) => x.id === e.target.value);
                                    add.setData((d) => ({
                                        ...d,
                                        market_product_id: e.target.value,
                                        name: p ? p.title : d.name,
                                    }));
                                }}
                            >
                                <option value="">{t('biz.stock.not_linked_option')}</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.title} ({p.stock})
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    )}
                    <Field label={t('biz.stock.name')}>
                        <Input value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} />
                    </Field>
                    <Field label={t('biz.stock.unit_label')}>
                        <Select value={add.data.unit} onChange={(e) => add.setData('unit', e.target.value)}>
                            {units.map((u) => (
                                <option key={u} value={u}>
                                    {t(`biz.stock.unit.${u}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {!add.data.market_product_id && (
                        <Field label={t('biz.stock.how_many_now')}>
                            <Input
                                type="number"
                                min={0}
                                inputMode="numeric"
                                value={add.data.quantity}
                                onChange={(e) => add.setData('quantity', e.target.value)}
                            />
                        </Field>
                    )}
                    <Field label={t('biz.stock.cost_each')}>
                        <Input
                            type="number"
                            min={0}
                            step="0.01"
                            inputMode="decimal"
                            value={add.data.cost}
                            onChange={(e) => add.setData('cost', e.target.value)}
                        />
                    </Field>
                    <Field label={t('biz.stock.reorder_label')} hint={t('biz.stock.reorder_hint')}>
                        <Input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={add.data.reorder_level}
                            onChange={(e) => add.setData('reorder_level', e.target.value)}
                        />
                    </Field>
                    <div className="flex items-end">
                        <Button type="submit" disabled={!add.data.name.trim()} loading={add.processing}>
                            {t('biz.stock.add_button')}
                        </Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
