import { Head, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface MentorRow {
    id: string;
    name: string;
    headline: string;
    status: string;
    reason: string | null;
    minorsOk: boolean;
    idVerified: boolean;
    checks: Record<string, string | null>;
}

export default function Coordinator({ mentors, checks }: { mentors: MentorRow[]; checks: string[] }) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const decide = (id: string, action: string) => {
        const reason = action === 'approve' ? null : window.prompt(t('connect.coordinator.reason'));
        if (action !== 'approve' && !reason) return;
        router.post(`/connect/coordinator/${id}/decide`, { action, reason }, { preserveScroll: true });
    };
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('nav.connect.coordinator')} />
            <h1 className="text-fg text-2xl font-bold">{t('nav.connect.coordinator')}</h1>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.mentor && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.mentor} />
                </div>
            )}
            <ul className="mt-6 flex flex-col gap-4">
                {mentors.map((m) => (
                    <li key={m.id}>
                        <Card>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <span className="text-fg font-semibold">{m.name}</span>{' '}
                                    <span className="text-fg-muted text-sm">{m.headline}</span>
                                </span>
                                <span className="flex gap-1">
                                    {m.minorsOk && <Badge tone="primary">{t('connect.coordinator.minors_ok')}</Badge>}
                                    <Badge
                                        tone={
                                            m.status === 'approved'
                                                ? 'success'
                                                : m.status === 'applied'
                                                  ? 'warning'
                                                  : 'danger'
                                        }
                                    >
                                        {t(`connect.mentor.status_${m.status}`)}
                                    </Badge>
                                </span>
                            </div>
                            {m.reason && <p className="text-fg-muted text-sm">{m.reason}</p>}
                            <div className="mt-3 grid gap-x-4 sm:grid-cols-2">
                                {checks.map((c) => (
                                    <Checkbox
                                        key={c}
                                        label={`${t(`connect.check.${c}`)}${c === 'id_verified' && !m.idVerified ? ` (${t('connect.coordinator.id_not_verified')})` : ''}`}
                                        checked={c in m.checks}
                                        onCheckedChange={(v) =>
                                            router.post(
                                                `/connect/coordinator/${m.id}/check`,
                                                {
                                                    check: c,
                                                    done: v === true,
                                                    note:
                                                        v === true
                                                            ? window.prompt(t('connect.coordinator.note'))
                                                            : null,
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                ))}
                            </div>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {m.status !== 'approved' && (
                                    <Button size="sm" onClick={() => decide(m.id, 'approve')}>
                                        {t('connect.coordinator.approve')}
                                    </Button>
                                )}
                                {m.status === 'approved' && (
                                    <Button size="sm" variant="secondary" onClick={() => decide(m.id, 'pause')}>
                                        {t('connect.coordinator.pause')}
                                    </Button>
                                )}
                                {m.status !== 'removed' && (
                                    <Button size="sm" variant="ghost" onClick={() => decide(m.id, 'remove')}>
                                        {t('connect.coordinator.remove')}
                                    </Button>
                                )}
                            </div>
                        </Card>
                    </li>
                ))}
            </ul>
        </AppLayout>
    );
}
