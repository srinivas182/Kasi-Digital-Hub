import { Link, router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Button } from '@/components/ui/Button';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage, PageLinks, type Paginated } from '../components/AdminPage';
import { ReasonDialog } from '../components/ReasonDialog';

interface Flag {
    id: string;
    type: string;
    excerpt: string;
    reasons: string[];
    source: string;
    author: string | null;
    authorId: string | null;
    decision: string | null;
    link: string | null;
    at: string;
}

const STATUSES = ['pending', 'escalated', 'approved', 'rejected'];

export default function Moderation({ status, flags }: { status: string; flags: Paginated<Flag> }) {
    const { t } = useTranslation();
    const open = status === 'pending' || status === 'escalated';

    return (
        <AdminPage title={t('admin.moderation.title')} crumbs={[{ label: t('admin.moderation.title') }]}>
            <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label={t('admin.filter')}>
                {STATUSES.map((s) => (
                    <button
                        key={s}
                        type="button"
                        aria-pressed={status === s}
                        onClick={() => router.get('/admin/moderation', { status: s })}
                        className={
                            status === s
                                ? 'bg-primary text-primary-fg rounded-full px-4 py-2 text-sm font-semibold'
                                : 'border-line text-fg hover:bg-surface-muted rounded-full border px-4 py-2 text-sm font-semibold'
                        }
                    >
                        {t(`admin.moderation.status.${s}`)}
                    </button>
                ))}
            </div>
            {flags.data.length === 0 ? (
                <EmptyState icon={<CheckCircle2 className="size-8" aria-hidden />} title={t('admin.moderation.none')} />
            ) : (
                <ul className="flex flex-col gap-3" aria-label={t('admin.moderation.title')}>
                    {flags.data.map((f) => (
                        <li key={f.id}>
                            <Card>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge tone="primary">{t(`admin.moderation.type.${f.type}`)}</Badge>
                                    <Badge>{t(`admin.moderation.source.${f.source}`)}</Badge>
                                    <span className="text-fg-muted text-sm">
                                        {f.author ?? '-'} · {formatDateTime(f.at)}
                                    </span>
                                </div>
                                <ul className="text-danger-text mt-2 list-disc pl-5 text-sm">
                                    {f.reasons.map((r) => (
                                        <li key={r}>{r}</li>
                                    ))}
                                </ul>
                                <blockquote className="border-line text-fg mt-3 border-l-4 pl-3 whitespace-pre-line">
                                    {f.excerpt}
                                </blockquote>
                                {f.decision && <p className="text-fg-muted mt-2 text-sm">{f.decision}</p>}
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {f.link && (
                                        <Link
                                            href={f.link}
                                            className="text-primary self-center text-sm font-semibold hover:underline"
                                        >
                                            {t('admin.moderation.open')}
                                        </Link>
                                    )}
                                    {open &&
                                        (['approved', 'rejected', 'escalated'] as const)
                                            .filter((d) => !(status === 'escalated' && d === 'escalated'))
                                            .map((decision) => (
                                                <ReasonDialog
                                                    key={decision}
                                                    trigger={
                                                        <Button
                                                            size="sm"
                                                            variant={
                                                                decision === 'rejected'
                                                                    ? 'danger'
                                                                    : decision === 'approved'
                                                                      ? 'primary'
                                                                      : 'secondary'
                                                            }
                                                        >
                                                            {t(
                                                                `admin.moderation.${decision === 'approved' ? 'approve' : decision === 'rejected' ? 'reject' : 'escalate'}`,
                                                            )}
                                                        </Button>
                                                    }
                                                    title={t(
                                                        `admin.moderation.${decision === 'approved' ? 'approve' : decision === 'rejected' ? 'reject' : 'escalate'}`,
                                                    )}
                                                    confirmLabel={t(
                                                        `admin.moderation.${decision === 'approved' ? 'approve' : decision === 'rejected' ? 'reject' : 'escalate'}`,
                                                    )}
                                                    action={`/admin/moderation/${f.id}`}
                                                    extra={{ decision }}
                                                    danger={decision === 'rejected'}
                                                />
                                            ))}
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
            <PageLinks page={flags} />
        </AdminPage>
    );
}
