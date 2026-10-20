import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Debt {
    id: number;
    cents: number;
    description: string;
    due: string | null;
    since: string;
    name: string;
    phone: string | null;
}

interface Props {
    business: { id: string; name: string };
    open: { owed_me: Debt[]; i_owe: Debt[] };
    contacts: { id: number; kind: string; name: string; phone: string | null }[];
    methods: string[];
}

/** Who owes what (S28): money owed to the business and money it owes, oldest first. */
export default function Owed({ business, open, contacts, methods }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [method, setMethod] = useState<Record<number, string>>({});
    const contact = useForm({ kind: 'customer', name: '', phone: '' });
    const debt = useForm({
        contact_id: String(contacts[0]?.id ?? ''),
        direction: 'owed_me',
        amount: '',
        description: '',
        due_on: '',
    });
    const reminder = (d: Debt) =>
        `https://wa.me/${(d.phone ?? '').replace('+', '')}?text=${encodeURIComponent(t('biz.owed.reminder_text', { name: d.name, business: business.name, amount: formatMoney(d.cents), what: d.description }))}`;
    const list = (rows: Debt[], mine: boolean) => (
        <ul className="mt-2 flex flex-col">
            {rows.map((d) => (
                <li
                    key={d.id}
                    className="border-line flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm"
                >
                    <span>
                        <span className="text-fg font-semibold">
                            {d.name} · {formatMoney(d.cents)}
                        </span>
                        <span className="text-fg-muted block">
                            {d.description} · {t('biz.owed.since', { date: formatDate(d.since) })}
                            {d.due ? ` · ${t('biz.owed.due', { date: formatDate(d.due) })}` : ''}
                        </span>
                    </span>
                    <span className="flex flex-wrap items-center gap-2">
                        {mine && d.phone && (
                            <a
                                href={reminder(d)}
                                rel="noopener"
                                className="text-primary inline-flex items-center gap-1 font-semibold hover:underline"
                            >
                                <MessageCircle className="size-4" aria-hidden /> {t('biz.owed.remind')}
                            </a>
                        )}
                        <Select
                            aria-label={t('biz.how_paid')}
                            value={method[d.id] ?? 'cash'}
                            onChange={(e) => setMethod({ ...method, [d.id]: e.target.value })}
                        >
                            {methods.map((m) => (
                                <option key={m} value={m}>
                                    {t(`biz.method.${m}`)}
                                </option>
                            ))}
                        </Select>
                        <Button
                            size="sm"
                            onClick={() =>
                                router.post(
                                    `/biz/owed/${d.id}/paid`,
                                    { business: business.id, method: method[d.id] ?? 'cash' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('biz.owed.mark_paid')}
                        </Button>
                    </span>
                </li>
            ))}
            {rows.length === 0 && <li className="text-fg-muted py-2 text-sm">{t('biz.owed.nothing')}</li>}
        </ul>
    );
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('biz.owed.title')} />
            <Link href={`/biz?business=${business.id}`} className="text-primary text-sm font-semibold hover:underline">
                ← {business.name}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('biz.owed.title')}</h1>
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
            <div className="mt-4 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('biz.owed.owed_me')}</CardTitle>
                    {list(open.owed_me, true)}
                </Card>
                <Card>
                    <CardTitle>{t('biz.owed.i_owe')}</CardTitle>
                    {list(open.i_owe, false)}
                </Card>
                <Card>
                    <CardTitle>{t('biz.owed.add')}</CardTitle>
                    {contacts.length === 0 ? (
                        <p className="text-fg-muted mt-2 text-sm">{t('biz.owed.add_person_first')}</p>
                    ) : (
                        <form
                            className="mt-2 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                debt.transform((d) => ({
                                    business: business.id,
                                    contact_id: Number(d.contact_id),
                                    direction: d.direction,
                                    amount_cents: Math.round(Number(d.amount) * 100),
                                    description: d.description,
                                    due_on: d.due_on || null,
                                }));
                                debt.post('/biz/owed', {
                                    preserveScroll: true,
                                    onSuccess: () => debt.reset('amount', 'description', 'due_on'),
                                });
                            }}
                        >
                            <Field label={t('biz.owed.who')}>
                                <Select
                                    value={debt.data.contact_id}
                                    onChange={(e) => debt.setData('contact_id', e.target.value)}
                                >
                                    {contacts.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name} ({t(`biz.owed.kind.${c.kind}`)})
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('biz.owed.which_way')}>
                                <Select
                                    value={debt.data.direction}
                                    onChange={(e) => debt.setData('direction', e.target.value)}
                                >
                                    <option value="owed_me">{t('biz.owed.they_owe_me')}</option>
                                    <option value="i_owe">{t('biz.owed.i_owe_them')}</option>
                                </Select>
                            </Field>
                            <Field label={t('biz.amount')}>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={debt.data.amount}
                                    onChange={(e) => debt.setData('amount', e.target.value)}
                                />
                            </Field>
                            <Field label={t('biz.owed.for_what')}>
                                <Input
                                    value={debt.data.description}
                                    onChange={(e) => debt.setData('description', e.target.value)}
                                />
                            </Field>
                            <Field label={t('biz.owed.due_label')}>
                                <Input
                                    type="date"
                                    value={debt.data.due_on}
                                    onChange={(e) => debt.setData('due_on', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={!debt.data.amount || !debt.data.description.trim()}
                                >
                                    {t('biz.save')}
                                </Button>
                            </div>
                        </form>
                    )}
                </Card>
                <Card>
                    <CardTitle>{t('biz.owed.add_person')}</CardTitle>
                    <form
                        className="mt-2 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            contact.transform((d) => ({ ...d, business: business.id }));
                            contact.post('/biz/contacts', {
                                preserveScroll: true,
                                onSuccess: () => contact.reset('name', 'phone'),
                            });
                        }}
                    >
                        <Field label={t('biz.owed.kind_label')}>
                            <Select value={contact.data.kind} onChange={(e) => contact.setData('kind', e.target.value)}>
                                <option value="customer">{t('biz.owed.kind.customer')}</option>
                                <option value="supplier">{t('biz.owed.kind.supplier')}</option>
                            </Select>
                        </Field>
                        <Field label={t('biz.owed.name')}>
                            <Input
                                value={contact.data.name}
                                onChange={(e) => contact.setData('name', e.target.value)}
                            />
                        </Field>
                        <Field label={t('biz.owed.phone')} hint={t('biz.owed.phone_hint')}>
                            <Input
                                type="tel"
                                value={contact.data.phone}
                                onChange={(e) => contact.setData('phone', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button type="submit" size="sm" variant="secondary" disabled={!contact.data.name.trim()}>
                                {t('biz.owed.add_person')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
