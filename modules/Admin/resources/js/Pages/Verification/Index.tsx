import { router, useForm } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { Field, Select, Textarea } from '@/components/ui/form';
import { cn } from '@/lib/cn';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage, PageLinks, type Paginated } from '../../components/AdminPage';

interface QueueItem {
    id: string;
    type: string;
    owner: string | null;
    ownerId: string;
    mime: string;
    uploadedAt: string;
    waitingHours: number;
    expiresOn: string | null;
    previewUrl: string;
}

interface Props {
    filters: { type: string | null; hub: string | null };
    documents: Paginated<QueueItem>;
    types: string[];
    hubs: { id: string; name: string }[];
    reasons: string[];
}

/** Review queue: list on the left, document preview and decision on the right. */
export default function VerificationIndex({ filters, documents, types, hubs, reasons }: Props) {
    const { t } = useTranslation();
    const [selectedId, setSelectedId] = useState<string | null>(documents.data[0]?.id ?? null);
    const selected = documents.data.find((d) => d.id === selectedId) ?? documents.data[0] ?? null;
    const reject = useForm({ reason: reasons[0] ?? 'other', note: '' });
    const filter = (key: string, value: string) =>
        router.get('/admin/verification', { ...filters, [key]: value || undefined }, { preserveState: false });

    return (
        <AdminPage title={t('admin.verification.title')} crumbs={[{ label: t('admin.verification.title') }]}>
            <div className="mb-4 flex flex-wrap gap-3">
                <Select
                    aria-label={t('documents.type')}
                    className="max-w-56"
                    value={filters.type ?? ''}
                    onChange={(e) => filter('type', e.target.value)}
                >
                    <option value="">{t('admin.all')}</option>
                    {types.map((type) => (
                        <option key={type} value={type}>
                            {t(`documents.type.${type}`)}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label={t('admin.people.col.hub')}
                    className="max-w-56"
                    value={filters.hub ?? ''}
                    onChange={(e) => filter('hub', e.target.value)}
                >
                    <option value="">{t('admin.all')}</option>
                    {hubs.map((h) => (
                        <option key={h.id} value={h.id}>
                            {h.name}
                        </option>
                    ))}
                </Select>
            </div>

            {documents.data.length === 0 || !selected ? (
                <EmptyState
                    icon={<CheckCircle2 className="size-8" aria-hidden />}
                    title={t('admin.verification.empty')}
                />
            ) : (
                <div className="grid gap-6 lg:grid-cols-[20rem_1fr]">
                    <div>
                        <ul className="flex flex-col gap-2" aria-label={t('admin.verification.title')}>
                            {documents.data.map((d) => (
                                <li key={d.id}>
                                    <button
                                        type="button"
                                        aria-current={d.id === selected.id || undefined}
                                        onClick={() => setSelectedId(d.id)}
                                        className={cn(
                                            'rounded-card border-line bg-surface hover:bg-surface-muted w-full border p-3 text-left',
                                            d.id === selected.id && 'border-primary bg-primary-soft',
                                        )}
                                    >
                                        <span className="text-fg block text-sm font-semibold">
                                            {t(`documents.type.${d.type}`)}
                                        </span>
                                        <span className="text-fg-muted block text-sm">{d.owner}</span>
                                        <Badge tone={d.waitingHours > 48 ? 'danger' : 'neutral'} className="mt-1">
                                            {t('admin.verification.waiting', { hours: d.waitingHours })}
                                        </Badge>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <PageLinks page={documents} />
                    </div>
                    <Card>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-fg text-lg font-semibold">{t(`documents.type.${selected.type}`)}</p>
                                <p className="text-fg-muted text-sm">
                                    {selected.owner} · {formatDateTime(selected.uploadedAt)}
                                </p>
                            </div>
                            <a
                                href={selected.previewUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary text-sm font-semibold hover:underline"
                            >
                                {t('documents.open')}
                            </a>
                        </div>
                        <div className="border-line bg-surface-muted mt-4 overflow-hidden rounded-lg border">
                            {selected.mime.startsWith('image/') ? (
                                <img
                                    src={selected.previewUrl}
                                    alt={t('admin.verification.preview')}
                                    className="mx-auto max-h-[32rem]"
                                />
                            ) : (
                                <iframe
                                    key={selected.id}
                                    src={selected.previewUrl}
                                    title={t('admin.verification.preview')}
                                    className="h-[32rem] w-full"
                                    sandbox=""
                                />
                            )}
                        </div>
                        <div className="mt-6 grid gap-4 md:grid-cols-[auto_1fr]">
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/admin/verification/${selected.id}/verify`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('admin.verification.verify')}
                            </Button>
                            <form
                                className="border-line flex flex-col gap-3 md:border-l md:pl-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    reject.post(`/admin/verification/${selected.id}/reject`, {
                                        preserveScroll: true,
                                        onSuccess: () => reject.reset('note'),
                                    });
                                }}
                            >
                                <Field label={t('admin.verification.reason_label')} error={reject.errors.reason}>
                                    <Select
                                        value={reject.data.reason}
                                        onChange={(e) => reject.setData('reason', e.target.value)}
                                    >
                                        {reasons.map((r) => (
                                            <option key={r} value={r}>
                                                {t(`admin.verification.reason.${r}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('admin.verification.note')} error={reject.errors.note}>
                                    <Textarea
                                        rows={2}
                                        value={reject.data.note}
                                        onChange={(e) => reject.setData('note', e.target.value)}
                                    />
                                </Field>
                                <div>
                                    <Button type="submit" variant="danger" loading={reject.processing}>
                                        {t('admin.verification.reject')}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </Card>
                </div>
            )}
        </AdminPage>
    );
}
