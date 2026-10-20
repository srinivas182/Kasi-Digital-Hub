import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Props {
    insights: {
        weeks: { week: string; orders: number; cents: number }[];
        top: { title: string; quantity: number; cents: number }[];
        views: number;
        orders: number;
        conversion: number | null;
        repeat_buyers: number;
        rating: number | null;
        reviews: number;
        online_cents: number;
        cash_cents: number;
    };
    signals: {
        orders: number;
        median_accept_hours: number | null;
        acceptance: number | null;
        lapsed: number;
        completion: number | null;
    };
    days: number;
    quick: boolean;
    strikes: number;
    reviews: { id: number; rating: number; body: string | null; shown: boolean; reply: string | null }[];
}

function ReplyForm({ id }: { id: number }) {
    const { t } = useTranslation();
    const form = useForm({ reply: '' });
    return (
        <form
            className="mt-2 flex flex-col gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/market/shop/reviews/${id}/reply`, { preserveScroll: true });
            }}
        >
            <Field label={t('market.review.reply_label')}>
                <Textarea
                    rows={2}
                    maxLength={500}
                    value={form.data.reply}
                    onChange={(e) => form.setData('reply', e.target.value)}
                />
            </Field>
            <div>
                <Button type="submit" size="sm" variant="secondary" disabled={!form.data.reply.trim()}>
                    {t('market.review.reply_send')}
                </Button>
            </div>
        </form>
    );
}

/** How the shop is doing (S27): sales, top products, conversion, reliability, reviews. */
export default function Insights({ insights: i, signals: s, days, quick, strikes, reviews }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const stat = (label: string, value: string) => (
        <Card>
            <p className="text-fg-muted text-sm">{label}</p>
            <p className="text-fg text-2xl font-bold">{value}</p>
        </Card>
    );
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('market.insights.title')} />
            <Link href="/market/shop" className="text-primary text-sm font-semibold hover:underline">
                ← {t('market.dash.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('market.insights.title')}</h1>
                <span className="flex gap-2">
                    {[30, 90].map((d) => (
                        <Button
                            key={d}
                            size="sm"
                            variant={d === days ? 'primary' : 'secondary'}
                            onClick={() => router.get('/market/shop/insights', { days: d })}
                        >
                            {t('market.insights.days', { days: d })}
                        </Button>
                    ))}
                    <a
                        href="/market/shop/insights.csv"
                        className="text-primary self-center text-sm font-semibold hover:underline"
                    >
                        {t('market.insights.csv')}
                    </a>
                </span>
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {strikes > 0 && (
                <div className="mt-3">
                    <Alert tone="warning" title={t('market.insights.strikes', { count: strikes })} />
                </div>
            )}
            <div className="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {stat(t('market.insights.orders'), String(i.orders))}
                {stat(t('market.insights.conversion'), i.conversion === null ? '-' : `${i.conversion}%`)}
                {stat(t('market.insights.online'), formatMoney(i.online_cents))}
                {stat(t('market.insights.cash'), formatMoney(i.cash_cents))}
                {stat(t('market.insights.views'), String(i.views))}
                {stat(t('market.insights.repeat'), String(i.repeat_buyers))}
                {stat(
                    t('market.insights.rating'),
                    i.rating === null ? t('market.review.too_few_short') : `${i.rating} / 5`,
                )}
                {stat(t('market.insights.reviews'), String(i.reviews))}
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('market.insights.by_week')}</CardTitle>
                    <table className="mt-2 w-full text-sm">
                        <thead>
                            <tr className="text-fg-muted text-left">
                                <th scope="col">{t('market.insights.week')}</th>
                                <th scope="col">{t('market.insights.orders')}</th>
                                <th scope="col">{t('market.insights.amount')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {i.weeks.map((w) => (
                                <tr key={w.week} className="text-fg">
                                    <td>{w.week}</td>
                                    <td>{w.orders}</td>
                                    <td>{formatMoney(w.cents)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <CardTitle className="mt-4">{t('market.insights.top')}</CardTitle>
                    <ul className="mt-1 text-sm">
                        {i.top.map((p) => (
                            <li key={p.title} className="text-fg">
                                {p.title}: {p.quantity} · {formatMoney(p.cents)}
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card>
                    <CardTitle>{t('market.insights.reliability')}</CardTitle>
                    {quick && <Badge tone="success">{t('market.badge.quick')}</Badge>}
                    <ul className="text-fg mt-2 flex flex-col gap-1 text-sm">
                        <li>{t('market.insights.accept_time', { hours: s.median_accept_hours ?? '-' })}</li>
                        <li>{t('market.insights.acceptance', { pct: s.acceptance ?? '-' })}</li>
                        <li>{t('market.insights.completion', { pct: s.completion ?? '-' })}</li>
                        <li>{t('market.insights.lapsed', { count: s.lapsed })}</li>
                    </ul>
                    <p className="text-fg-muted mt-2 text-xs">{t('market.insights.private')}</p>
                </Card>
            </div>
            <Card className="mt-6">
                <CardTitle>{t('market.review.heading')}</CardTitle>
                <ul className="mt-2 flex flex-col gap-3 text-sm">
                    {reviews.map((r) => (
                        <li key={r.id} className="border-line border-b pb-2">
                            <p className="text-fg">
                                {t('market.review.n_stars', { n: r.rating })} {r.body}
                                {!r.shown && (
                                    <span className="text-fg-muted block text-xs">
                                        {t('market.message.held_badge')}
                                    </span>
                                )}
                            </p>
                            {r.reply ? (
                                <p className="text-fg-muted">{t('market.review.your_reply', { reply: r.reply })}</p>
                            ) : (
                                <ReplyForm id={r.id} />
                            )}
                        </li>
                    ))}
                    {reviews.length === 0 && <li className="text-fg-muted">{t('market.review.none')}</li>}
                </ul>
            </Card>
        </AppLayout>
    );
}
