import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Concern {
    id: string;
    kind: string;
    urgent: boolean;
    body: string;
    status: string;
    actions: string | null;
    at: string;
    overdue: boolean;
    about: string | null;
    aboutId: string | null;
    reporter: string | null;
}

function ConcernCard({ c }: { c: Concern }) {
    const { t } = useTranslation();
    const [actions, setActions] = useState('');
    const [pause, setPause] = useState(false);
    const [close, setClose] = useState(false);
    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="flex gap-1">
                    {c.urgent && <Badge tone="danger">{t('connect.concern.urgent_badge')}</Badge>}
                    {c.overdue && <Badge tone="danger">{t('connect.concern.overdue')}</Badge>}
                    <Badge>{t(`connect.concern.kind_${c.kind}`)}</Badge>
                    <Badge tone={c.status === 'closed' ? 'success' : 'warning'}>
                        {t(`connect.concern.status_${c.status}`)}
                    </Badge>
                </span>
                <span className="text-fg-muted text-sm">{formatDateTime(c.at)}</span>
            </div>
            <p className="text-fg mt-2 text-sm">
                {t('connect.concern.from_about', { reporter: c.reporter ?? '-', about: c.about ?? '-' })}
            </p>
            <p className="text-fg mt-2 whitespace-pre-line">{c.body}</p>
            {c.actions && <p className="text-fg-muted mt-2 text-sm whitespace-pre-line">{c.actions}</p>}
            {c.status !== 'closed' && (
                <div className="mt-3 flex flex-col gap-2">
                    <Field label={t('connect.concern.actions')}>
                        <Textarea rows={2} value={actions} onChange={(e) => setActions(e.target.value)} />
                    </Field>
                    {c.aboutId && (
                        <Checkbox
                            label={t('connect.concern.pause_mentor')}
                            checked={pause}
                            onCheckedChange={(v) => setPause(v === true)}
                        />
                    )}
                    <Checkbox
                        label={t('connect.concern.close')}
                        checked={close}
                        onCheckedChange={(v) => setClose(v === true)}
                    />
                    <div>
                        <Button
                            size="sm"
                            disabled={actions.trim().length < 5}
                            onClick={() =>
                                router.post(
                                    `/connect/safeguarding/${c.id}`,
                                    { actions, pause_mentor: pause, close },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('work.save')}
                        </Button>
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function Safeguarding({ concerns }: { concerns: Concern[] }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('nav.connect.safeguarding')} />
            <h1 className="text-fg text-2xl font-bold">{t('nav.connect.safeguarding')}</h1>
            <p className="text-fg-muted mt-1 text-sm">{t('connect.concern.officer_lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 flex flex-col gap-4">
                {concerns.map((c) => (
                    <ConcernCard key={c.id} c={c} />
                ))}
                {concerns.length === 0 && <p className="text-fg-muted">{t('learn.review.none')}</p>}
            </div>
        </AppLayout>
    );
}
