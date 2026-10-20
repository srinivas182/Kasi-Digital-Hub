import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    shop: { slug: string; name: string; status: string; suspendedReason: string | null };
    strikes: { reason: string; source: string; at: string; active: boolean }[];
    reports: { reason: string; note: string | null; at: string }[];
    active: number;
    limit: number;
}

/** KasiHub moderators: a shop's reports and strikes; strike, suspend, restore (S27). */
export default function Moderate({ shop, strikes, reports, active, limit }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({ action: 'strike', reason: '', source: 'report' });
    const actions = shop.status === 'suspended' ? ['lift'] : active >= limit ? ['strike', 'suspend'] : ['strike'];
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={shop.name} />
            <h1 className="text-fg text-2xl font-bold">{shop.name}</h1>
            <p className="mt-1 flex gap-2">
                <Badge tone={shop.status === 'open' ? 'success' : 'danger'}>
                    {t(`market.enforce.status.${shop.status}`)}
                </Badge>
                <Badge tone={active >= limit ? 'danger' : 'neutral'}>
                    {t('market.enforce.active', { count: active, limit })}
                </Badge>
            </p>
            {shop.suspendedReason && <p className="text-fg mt-2 text-sm">{shop.suspendedReason}</p>}
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.trust && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.trust} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('market.enforce.reports')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {reports.map((r, n) => (
                            <li key={n} className="text-fg">
                                {formatDateTime(r.at)} · {t(`market.report.reason.${r.reason}`)}
                                {r.note ? `: ${r.note}` : ''}
                            </li>
                        ))}
                        {reports.length === 0 && <li className="text-fg-muted">{t('market.trader_orders.none')}</li>}
                    </ul>
                    <CardTitle className="mt-4">{t('market.enforce.strikes')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {strikes.map((s, n) => (
                            <li key={n} className={s.active ? 'text-fg' : 'text-fg-muted'}>
                                {formatDateTime(s.at)} · {s.reason} ({t(`market.enforce.source.${s.source}`)})
                                {s.active ? '' : ` · ${t('market.enforce.expired')}`}
                            </li>
                        ))}
                        {strikes.length === 0 && <li className="text-fg-muted">{t('market.trader_orders.none')}</li>}
                    </ul>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('market.enforce.action')}</CardTitle>
                    <form
                        className="mt-2 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            // Always send the action that is shown (a suspended shop can only be restored).
                            form.transform((d) => ({
                                ...d,
                                action: actions.includes(d.action) ? d.action : actions[0],
                            }));
                            form.post(`/market/moderate/${shop.slug}`, {
                                preserveScroll: true,
                                onSuccess: () => form.reset('reason'),
                            });
                        }}
                    >
                        <Field label={t('market.enforce.what')}>
                            <Select
                                value={actions.includes(form.data.action) ? form.data.action : actions[0]}
                                onChange={(e) => form.setData('action', e.target.value)}
                            >
                                {actions.map((a) => (
                                    <option key={a} value={a}>
                                        {t(`market.enforce.do.${a}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        {form.data.action === 'strike' && (
                            <Field label={t('market.enforce.source_label')}>
                                <Select
                                    value={form.data.source}
                                    onChange={(e) => form.setData('source', e.target.value)}
                                >
                                    {['report', 'dispute', 'other'].map((s) => (
                                        <option key={s} value={s}>
                                            {t(`market.enforce.source.${s}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        )}
                        <Field label={t('market.enforce.reason')} hint={t('market.enforce.reason_hint')}>
                            <Textarea
                                rows={2}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button
                                type="submit"
                                variant={form.data.action === 'suspend' ? 'danger' : 'primary'}
                                disabled={form.data.reason.trim().length < 5}
                                loading={form.processing}
                            >
                                {t(
                                    `market.enforce.do.${actions.includes(form.data.action) ? form.data.action : actions[0]}`,
                                )}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
