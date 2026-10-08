import { Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button, buttonVariants } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage, PageLinks, type Paginated } from '../../components/AdminPage';

interface LogRow {
    id: string;
    event: string;
    outcome: string;
    person: string | null;
    personId: string | null;
    actor: string | null;
    meta: Record<string, unknown> | null;
    ip: string | null;
    at: string;
}

export default function AuditIndex({
    filters,
    logs,
    canExport,
}: {
    filters: Record<string, string | undefined>;
    logs: Paginated<LogRow>;
    canExport: boolean;
}) {
    const { t } = useTranslation();
    const [values, setValues] = useState({
        event: filters.event ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        person: filters.person ?? '',
    });
    const query = new URLSearchParams(Object.entries(values).filter(([, v]) => v !== '')).toString();

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/audit', Object.fromEntries(Object.entries(values).filter(([, v]) => v !== '')), {
            preserveState: true,
        });
    };

    return (
        <AdminPage
            title={t('admin.audit.title')}
            crumbs={[{ label: t('admin.audit.title') }]}
            actions={
                canExport && (
                    <a
                        href={`/admin/audit/export${query ? `?${query}` : ''}`}
                        className={buttonVariants({ variant: 'secondary' })}
                    >
                        <Download className="size-4" aria-hidden /> {t('admin.audit.export')}
                    </a>
                )
            }
        >
            <form
                onSubmit={submit}
                className="rounded-card border-line bg-surface mb-6 grid gap-4 border p-4 md:grid-cols-4"
            >
                <Field label={t('admin.audit.event')}>
                    <Input
                        value={values.event}
                        placeholder="role."
                        onChange={(e) => setValues({ ...values, event: e.target.value })}
                    />
                </Field>
                <Field label={t('admin.audit.from')}>
                    <Input
                        type="date"
                        value={values.from}
                        onChange={(e) => setValues({ ...values, from: e.target.value })}
                    />
                </Field>
                <Field label={t('admin.audit.to')}>
                    <Input
                        type="date"
                        value={values.to}
                        onChange={(e) => setValues({ ...values, to: e.target.value })}
                    />
                </Field>
                <div className="flex items-end">
                    <Button type="submit">{t('admin.filter')}</Button>
                </div>
            </form>
            <DataTable<LogRow>
                caption={t('admin.audit.title')}
                rows={logs.data}
                rowKey={(l) => l.id}
                empty={<p className="text-fg-muted">{t('admin.none')}</p>}
                columns={[
                    { key: 'at', header: t('admin.audit.col.time'), cell: (l) => formatDateTime(l.at) },
                    {
                        key: 'event',
                        header: t('admin.audit.col.event'),
                        cell: (l) => (
                            <span className="flex items-center gap-2">
                                <span className="font-mono text-xs">{l.event}</span>
                                {l.outcome !== 'success' && <Badge tone="danger">{l.outcome}</Badge>}
                            </span>
                        ),
                    },
                    {
                        key: 'person',
                        header: t('admin.audit.col.person'),
                        cell: (l) =>
                            l.personId ? (
                                <Link href={`/admin/people/${l.personId}`} className="text-primary hover:underline">
                                    {l.person}
                                </Link>
                            ) : (
                                '-'
                            ),
                    },
                    { key: 'actor', header: t('admin.audit.col.by'), cell: (l) => l.actor ?? '-', hideOnMobile: true },
                    {
                        key: 'meta',
                        header: t('admin.audit.col.details'),
                        cell: (l) => (
                            <span className="text-fg-muted font-mono text-xs break-all">
                                {l.meta ? JSON.stringify(l.meta) : ''}
                            </span>
                        ),
                        hideOnMobile: true,
                    },
                ]}
            />
            <PageLinks page={logs} />
        </AdminPage>
    );
}
