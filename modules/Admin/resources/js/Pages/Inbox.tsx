import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Item {
    type: string;
    id: string;
    title: string;
    reason: string;
    since: string;
    url: string;
    bulk: boolean;
    overdue: boolean;
}

export default function Inbox({ items, counts }: { items: Item[]; counts: Record<string, number> }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const [selected, setSelected] = useState<string[]>([]);
    const [reason, setReason] = useState('');
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('inbox.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('inbox.title')}</h1>
            <p className="text-fg-muted mt-1 flex flex-wrap gap-2 text-sm">
                {Object.entries(counts).map(([k, v]) => (
                    <Badge key={k} tone="neutral">
                        {t(`inbox.type.${k}`)}: {v}
                    </Badge>
                ))}
            </p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <Card className="mt-4">
                <ul className="flex flex-col gap-2 text-sm">
                    {items.map((i) => (
                        <li
                            key={`${i.type}-${i.id}`}
                            className="border-line flex flex-wrap items-start justify-between gap-2 border-b pb-2"
                        >
                            <span className="flex items-start gap-2">
                                {i.bulk && (
                                    <Checkbox
                                        label={<span className="sr-only">{t('inbox.select', { title: i.title })}</span>}
                                        checked={selected.includes(i.id)}
                                        onCheckedChange={(c) =>
                                            setSelected(
                                                c === true ? [...selected, i.id] : selected.filter((x) => x !== i.id),
                                            )
                                        }
                                    />
                                )}
                                <span>
                                    <Link href={i.url} className="text-primary font-semibold hover:underline">
                                        {i.title || t(`inbox.type.${i.type}`)}
                                    </Link>
                                    <span className="text-fg-muted block">{i.reason}</span>
                                </span>
                            </span>
                            <span className="flex items-center gap-1">
                                {i.overdue && <Badge tone="danger">{t('support.overdue')}</Badge>}
                                <Badge>{t(`inbox.type.${i.type}`)}</Badge>
                                <span className="text-fg-muted">{formatDateTime(i.since)}</span>
                            </span>
                        </li>
                    ))}
                    {items.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
                </ul>
                {selected.length > 0 && (
                    <div className="mt-4 flex flex-wrap items-end gap-2">
                        <Field label={t('inbox.reason')} className="min-w-64 flex-1">
                            <Input value={reason} onChange={(e) => setReason(e.target.value)} />
                        </Field>
                        <Button
                            disabled={reason.trim().length < 5}
                            onClick={() =>
                                router.post(
                                    '/admin/inbox/approve',
                                    { ids: selected, reason },
                                    { onSuccess: () => setSelected([]) },
                                )
                            }
                        >
                            {t('inbox.approve', { count: selected.length })}
                        </Button>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}
