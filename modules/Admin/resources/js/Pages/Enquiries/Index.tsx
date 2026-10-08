import { router } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Select } from '@/components/ui/form';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage, PageLinks, type Paginated } from '../../components/AdminPage';

interface EnquiryRow {
    id: string;
    kind: string;
    name: string;
    organisation: string | null;
    phone: string | null;
    email: string | null;
    topic: string | null;
    message: string;
    status: string;
    receivedAt: string | null;
}

export default function EnquiriesIndex({
    filters,
    enquiries,
    kinds,
}: {
    filters: { kind: string | null; status: string };
    enquiries: Paginated<EnquiryRow>;
    kinds: string[];
}) {
    const { t } = useTranslation();
    const filter = (key: string, value: string) =>
        router.get('/admin/enquiries', { ...filters, [key]: value || undefined }, { preserveState: true });
    const setStatus = (id: string, status: string) =>
        router.put(`/admin/enquiries/${id}`, { status }, { preserveScroll: true });

    return (
        <AdminPage title={t('admin.enquiries.title')} crumbs={[{ label: t('admin.enquiries.title') }]}>
            <div className="mb-4 flex flex-wrap gap-3">
                <Select
                    aria-label={t('admin.hubs.field.status')}
                    className="max-w-48"
                    value={filters.status}
                    onChange={(e) => filter('status', e.target.value)}
                >
                    {['new', 'handled', 'spam'].map((s) => (
                        <option key={s} value={s}>
                            {t(`admin.enquiries.status.${s}`)}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label="Type"
                    className="max-w-56"
                    value={filters.kind ?? ''}
                    onChange={(e) => filter('kind', e.target.value)}
                >
                    <option value="">{t('admin.all')}</option>
                    {kinds.map((k) => (
                        <option key={k} value={k}>
                            {t(`admin.enquiries.kind.${k}`)}
                        </option>
                    ))}
                </Select>
            </div>
            {enquiries.data.length === 0 ? (
                <EmptyState title={t('admin.none')} />
            ) : (
                <ul className="flex flex-col gap-3" aria-label={t('admin.enquiries.title')}>
                    {enquiries.data.map((e) => (
                        <li key={e.id}>
                            <Card>
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="text-fg font-semibold">
                                            {e.name}
                                            {e.organisation ? ` · ${e.organisation}` : ''}
                                        </p>
                                        <p className="text-fg-muted text-sm">
                                            {[e.phone, e.email].filter(Boolean).join(' · ')}{' '}
                                            {e.receivedAt ? `· ${formatDateTime(e.receivedAt)}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Badge tone="primary">{t(`admin.enquiries.kind.${e.kind}`)}</Badge>
                                        {e.topic && <Badge>{t(`site.form.topic.${e.topic}`)}</Badge>}
                                    </div>
                                </div>
                                <p className="text-fg mt-3 whitespace-pre-line">{e.message}</p>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {e.status !== 'handled' && (
                                        <Button size="sm" onClick={() => setStatus(e.id, 'handled')}>
                                            {t('admin.enquiries.handled')}
                                        </Button>
                                    )}
                                    {e.status !== 'spam' && (
                                        <Button size="sm" variant="ghost" onClick={() => setStatus(e.id, 'spam')}>
                                            {t('admin.enquiries.spam')}
                                        </Button>
                                    )}
                                    {e.status !== 'new' && (
                                        <Button size="sm" variant="secondary" onClick={() => setStatus(e.id, 'new')}>
                                            {t('admin.enquiries.reopen')}
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
            <PageLinks page={enquiries} />
        </AdminPage>
    );
}
