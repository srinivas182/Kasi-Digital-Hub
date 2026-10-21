import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card, CardTitle } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { SummaryCard, type Summary } from '../components/SummaryCard';

interface Props {
    business: { id: string; name: string };
    preview: Summary;
    shares: { id: string; recipient: string | null; expires: string; withdrawn: boolean; views: number }[];
    newLink: string | null;
}

/** Share my summary (S29): bands only, by a 7-day link the owner can withdraw. */
export default function SharePage({ business, preview, shares, newLink }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const form = useForm({ recipient: '' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('biz.share.title')} />
            <Link href="/biz" className="text-primary text-sm font-semibold hover:underline">
                ← {business.name}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('biz.share.title')}</h1>
            <p className="text-fg-muted mt-1">{t('biz.share.lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {newLink && (
                <Card className="mt-3">
                    <p className="text-fg text-sm">{t('biz.share.link_once')}</p>
                    <p className="text-fg mt-1 font-mono text-sm break-all">{newLink}</p>
                    <a
                        href={`https://wa.me/?text=${encodeURIComponent(t('biz.share.whatsapp', { link: newLink }))}`}
                        rel="noopener"
                        className="text-primary mt-2 inline-block text-sm font-semibold hover:underline"
                    >
                        {t('biz.share.send_whatsapp')}
                    </a>
                </Card>
            )}
            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div>
                    <h2 className="text-fg text-lg font-bold">{t('biz.share.preview')}</h2>
                    <SummaryCard summary={preview} />
                </div>
                <div className="flex flex-col gap-4">
                    <Card>
                        <CardTitle>{t('biz.share.create')}</CardTitle>
                        <form
                            className="mt-2 flex flex-col gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/biz/share', { preserveScroll: true, onSuccess: () => form.reset() });
                            }}
                        >
                            <Field label={t('biz.share.recipient')} hint={t('biz.share.recipient_hint')}>
                                <Input
                                    value={form.data.recipient}
                                    onChange={(e) => form.setData('recipient', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" size="sm" loading={form.processing}>
                                    {t('biz.share.make_link')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                    <Card>
                        <CardTitle>{t('biz.share.yours')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-2 text-sm">
                            {shares.map((s) => (
                                <li key={s.id} className="text-fg">
                                    {s.recipient ?? t('biz.share.no_recipient')} ·{' '}
                                    {t('biz.share.views', { count: s.views })}
                                    <span className="text-fg-muted block">
                                        {s.withdrawn
                                            ? t('biz.share.withdrawn_badge')
                                            : t('biz.share.until', { date: formatDateTime(s.expires) })}
                                    </span>
                                    {!s.withdrawn && (
                                        <button
                                            type="button"
                                            className="text-danger-text underline"
                                            onClick={() =>
                                                router.post(`/biz/share/${s.id}/withdraw`, {}, { preserveScroll: true })
                                            }
                                        >
                                            {t('biz.share.withdraw')}
                                        </button>
                                    )}
                                </li>
                            ))}
                            {shares.length === 0 && <li className="text-fg-muted">{t('biz.share.none')}</li>}
                        </ul>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
