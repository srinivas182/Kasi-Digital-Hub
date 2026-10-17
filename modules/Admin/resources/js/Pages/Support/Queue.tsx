import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Badge, Card } from '@/components/ui/display';
import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Row {
    id: string;
    number: string;
    category: string;
    subject: string;
    status: string;
    created_at: string;
    first_name: string | null;
    overdue: boolean;
}

export default function SupportQueue({
    status,
    tickets,
    counts,
}: {
    status: string;
    tickets: Row[];
    counts: Record<string, number>;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [personId, setPersonId] = useState('');
    const [reason, setReason] = useState('');
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('support.queue')} />
            <h1 className="text-fg text-2xl font-bold">{t('support.queue')}</h1>
            <div className="mt-3 flex flex-wrap gap-2" role="group" aria-label={t('support.filter')}>
                {['open', 'escalated', 'waiting', 'resolved', 'all'].map((s) => (
                    <Button
                        key={s}
                        size="sm"
                        variant={status === s ? 'primary' : 'secondary'}
                        aria-pressed={status === s}
                        onClick={() => router.get('/admin/support', { status: s })}
                    >
                        {t(`support.status.${s}`)} {counts[s] ? `(${counts[s]})` : ''}
                    </Button>
                ))}
            </div>
            <Card className="mt-4">
                <ul className="flex flex-col gap-2 text-sm">
                    {tickets.map((tk) => (
                        <li
                            key={tk.id}
                            className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                        >
                            <Link
                                href={`/admin/support/${tk.id}`}
                                className="text-primary font-semibold hover:underline"
                            >
                                {tk.number} · {tk.subject}
                            </Link>
                            <span className="flex flex-wrap items-center gap-1">
                                {tk.overdue && <Badge tone="danger">{t('support.overdue')}</Badge>}
                                <Badge>{t(`support.cat.${tk.category}`)}</Badge>
                                <span className="text-fg-muted">
                                    {tk.first_name} · {formatDateTime(tk.created_at)}
                                </span>
                            </span>
                        </li>
                    ))}
                    {tickets.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
                </ul>
            </Card>
            <Card className="mt-6 max-w-xl">
                <form
                    className="flex flex-col gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(`/admin/support/people/${personId.trim()}`, { reason });
                    }}
                >
                    <p className="text-fg text-sm font-semibold">{t('support.lookup')}</p>
                    <Field label={t('support.person_id')}>
                        <Input value={personId} onChange={(e) => setPersonId(e.target.value)} />
                    </Field>
                    <Field label={t('support.reason')} hint={t('support.reason_hint')}>
                        <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                    </Field>
                    <div>
                        <Button
                            type="submit"
                            size="sm"
                            variant="secondary"
                            disabled={!personId.trim() || reason.trim().length < 5}
                        >
                            {t('support.open_summary')}
                        </Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
