import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Summary {
    name: string;
    phone: string;
    status: string;
    locked: boolean;
    minor: boolean;
    joined: string | null;
    lastSeen: string | null;
    roles: string[];
    counts: Record<string, number>;
    notifications: { id: string; notification: string; channel: string; status: string; created_at: string }[];
}

export default function SupportPerson({ summary, personId }: { summary: Summary; personId: string; reason: string }) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const [action, setAction] = useState<'unlock' | 'phone_change'>('unlock');
    const [code, setCode] = useState('');
    const [newPhone, setNewPhone] = useState('');
    const [hubCheck, setHubCheck] = useState('');
    const base = `/admin/support/people/${personId}`;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={summary.name} />
            <h1 className="text-fg text-2xl font-bold">{summary.name}</h1>
            <p className="text-fg-muted">
                {summary.phone} · {t('support.joined', { date: summary.joined ?? '-' })} ·{' '}
                {t('support.last_seen', { date: summary.lastSeen ? formatDateTime(summary.lastSeen) : '-' })}
            </p>
            <div className="mt-2 flex flex-wrap gap-1">
                <Badge>{summary.status}</Badge>
                {summary.locked && <Badge tone="danger">{t('support.locked')}</Badge>}
                {summary.minor && <Badge tone="warning">{t('support.minor')}</Badge>}
                {summary.roles.map((r) => (
                    <Badge key={r} tone="neutral">
                        {t(`roles.${r}`)}
                    </Badge>
                ))}
            </div>
            <div className="mt-3">
                <Alert tone="info" title={t('support.privacy')} />
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.code && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.code} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('support.counts')}</CardTitle>
                    <dl className="mt-2 grid grid-cols-2 gap-1 text-sm">
                        {Object.entries(summary.counts).map(([k, v]) => (
                            <div key={k} className="contents">
                                <dt className="text-fg-muted">{t(`support.count.${k}`)}</dt>
                                <dd className="text-fg text-right">{v}</dd>
                            </div>
                        ))}
                    </dl>
                    <CardTitle className="mt-4">{t('support.notifications')}</CardTitle>
                    <ul className="mt-2 text-sm">
                        {summary.notifications.map((n) => (
                            <li key={n.id} className="text-fg flex justify-between gap-2">
                                <span>
                                    {n.notification} · {n.channel}
                                </span>
                                <span>
                                    <Badge tone={n.status === 'failed' ? 'danger' : 'neutral'}>{n.status}</Badge>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card>
                    <CardTitle>{t('support.account_help')}</CardTitle>
                    <div className="mt-2 flex gap-2" role="group" aria-label={t('support.account_help')}>
                        <Button
                            size="sm"
                            variant={action === 'unlock' ? 'primary' : 'secondary'}
                            aria-pressed={action === 'unlock'}
                            onClick={() => setAction('unlock')}
                        >
                            {t('support.unlock')}
                        </Button>
                        <Button
                            size="sm"
                            variant={action === 'phone_change' ? 'primary' : 'secondary'}
                            aria-pressed={action === 'phone_change'}
                            onClick={() => setAction('phone_change')}
                        >
                            {t('support.change_phone')}
                        </Button>
                    </div>
                    <p className="text-fg-muted mt-2 text-sm">{t(`support.how.${action}`)}</p>
                    {action === 'phone_change' && (
                        <Field label={t('support.new_phone')} className="mt-2">
                            <Input value={newPhone} onChange={(e) => setNewPhone(e.target.value)} />
                        </Field>
                    )}
                    <Button
                        size="sm"
                        variant="secondary"
                        className="mt-2"
                        onClick={() =>
                            router.post(
                                `${base}/code`,
                                { action, new_phone: newPhone || null },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('support.send_code')}
                    </Button>
                    <Field label={t('support.code')} className="mt-3">
                        <Input
                            inputMode="numeric"
                            maxLength={6}
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                        />
                    </Field>
                    {action === 'phone_change' && (
                        <Field label={t('support.hub_check')} hint={t('support.hub_check_hint')} className="mt-2">
                            <Textarea rows={2} value={hubCheck} onChange={(e) => setHubCheck(e.target.value)} />
                        </Field>
                    )}
                    <Button
                        size="sm"
                        className="mt-2"
                        disabled={code.length !== 6}
                        onClick={() =>
                            router.post(
                                `${base}/act`,
                                { action, code, new_phone: newPhone || null, hub_check: hubCheck || null },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('support.confirm')}
                    </Button>
                </Card>
            </div>
        </AppLayout>
    );
}
