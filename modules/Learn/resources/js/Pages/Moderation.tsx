import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Item {
    id: number;
    reason: string;
    status: string;
    course: string;
    submission: string;
    outcome: string;
    assessor: string;
    own: boolean;
    notes: string | null;
}

function ModerationItem({ item }: { item: Item }) {
    const { t } = useTranslation();
    const [notes, setNotes] = useState('');
    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <a href={`/learn/assess/${item.submission}`} className="text-primary font-semibold hover:underline">
                    {item.course}
                </a>
                <span className="flex gap-1">
                    <Badge>{t(`learn.moderation.reason.${item.reason}`)}</Badge>
                    <Badge tone={item.outcome === 'competent' ? 'success' : 'danger'}>
                        {t(`learn.assignment.status.${item.outcome}`)}
                    </Badge>
                </span>
            </div>
            <p className="text-fg-muted text-sm">{t('learn.moderation.assessed_by', { name: item.assessor })}</p>
            {item.status !== 'pending' ? (
                <p className="text-fg mt-2 text-sm">
                    <Badge tone={item.status === 'agreed' ? 'success' : 'warning'}>
                        {t(`learn.moderation.status.${item.status}`)}
                    </Badge>{' '}
                    {item.notes}
                </p>
            ) : item.own ? (
                <p className="text-fg-muted mt-2 text-sm">{t('learn.moderation.own_work')}</p>
            ) : (
                <div className="mt-3 flex flex-col gap-2">
                    <Field label={t('learn.moderation.notes')}>
                        <Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
                    </Field>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            disabled={notes.trim().length < 5}
                            onClick={() =>
                                router.post(
                                    `/learn/moderation/${item.id}`,
                                    { agree: true, notes },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('learn.moderation.agree')}
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={notes.trim().length < 5}
                            onClick={() =>
                                router.post(
                                    `/learn/moderation/${item.id}`,
                                    { agree: false, notes },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('learn.moderation.disagree')}
                        </Button>
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function Moderation({ items }: { items: Item[] }) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.moderation.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('learn.moderation.title')}</h1>
                <a href="/learn/moderation/report" className={buttonVariants({ variant: 'secondary' })}>
                    {t('learn.moderation.report')}
                </a>
            </div>
            <p className="text-fg-muted mt-1 text-sm">{t('learn.moderation.hint')}</p>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.moderation && (
                <div className="mt-4">
                    <Alert tone="danger" title={errors.moderation} />
                </div>
            )}
            <div className="mt-6 flex flex-col gap-4">
                {items.length === 0 && <p className="text-fg-muted">{t('learn.review.none')}</p>}
                {items.map((i) => (
                    <ModerationItem key={i.id} item={i} />
                ))}
            </div>
        </AppLayout>
    );
}
