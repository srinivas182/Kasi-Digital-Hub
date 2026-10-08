import { router, useForm } from '@inertiajs/react';
import { LogOut, ShieldOff, ShieldCheck } from 'lucide-react';
import { useMemo } from 'react';

import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Select, Textarea } from '@/components/ui/form';
import { formatDate, formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { AdminPage } from '../../components/AdminPage';
import { ReasonDialog } from '../../components/ReasonDialog';

interface Option {
    id: string | number;
    name: string;
}

interface Props {
    person: {
        id: string;
        name: string;
        preferredName: string | null;
        phone: string;
        email: string | null;
        dateOfBirth: string;
        ageBand: string;
        status: string;
        hub: string | null;
        place: string | null;
        joined: string | null;
        lastLogin: string | null;
        staff: boolean;
    };
    roles: { id: string; role: string; label: string; scopeType: string; where: string; national: boolean }[];
    devices: number;
    consents: { purpose: string; granted: boolean; channel: string; at: string }[];
    documents: { id: string; type: string; status: string; url: string | null }[];
    audit: { event: string; outcome: string; at: string; byStaff: boolean }[];
    roleOptions: { key: string; label: string; scope: string; national: boolean }[];
    scopeOptions: Record<string, Option[]>;
    can: { assignRoles: boolean; assignNational: boolean; suspend: boolean };
}

export default function PersonShow({
    person,
    roles,
    devices,
    consents,
    documents,
    audit,
    roleOptions,
    scopeOptions,
    can,
}: Props) {
    const { t } = useTranslation();
    const form = useForm({ role: '', scope_id: '', reason: '' });
    const selected = useMemo(() => roleOptions.find((r) => r.key === form.data.role), [roleOptions, form.data.role]);
    const needsScope = selected && !['self', 'national'].includes(selected.scope);
    const available = roleOptions.filter((r) => can.assignNational || !r.national);

    const facts: [string, string | null][] = [
        [t('admin.people.col.phone'), person.phone],
        ['Email', person.email],
        [t('account.date_of_birth'), formatDate(person.dateOfBirth)],
        [t('admin.people.col.hub'), person.hub],
        [t('profile.place'), person.place],
        [t('admin.people.col.joined'), person.joined ? formatDate(person.joined) : null],
        ['Last sign-in', person.lastLogin ? formatDateTime(person.lastLogin) : null],
    ];

    return (
        <AdminPage
            title={person.name}
            crumbs={[{ label: t('admin.people.title'), href: '/admin/people' }, { label: person.name }]}
            actions={
                can.suspend && (
                    <>
                        <Button
                            variant="secondary"
                            icon={<LogOut className="size-4" aria-hidden />}
                            onClick={() =>
                                router.post(`/admin/people/${person.id}/sign-out`, {}, { preserveScroll: true })
                            }
                        >
                            {t('admin.people.sign_out')}
                        </Button>
                        {person.status === 'suspended' ? (
                            <ReasonDialog
                                trigger={
                                    <Button icon={<ShieldCheck className="size-4" aria-hidden />}>
                                        {t('admin.people.reactivate')}
                                    </Button>
                                }
                                title={t('admin.people.reactivate')}
                                confirmLabel={t('admin.people.reactivate')}
                                action={`/admin/people/${person.id}/reactivate`}
                            />
                        ) : (
                            <ReasonDialog
                                trigger={
                                    <Button variant="danger" icon={<ShieldOff className="size-4" aria-hidden />}>
                                        {t('admin.people.suspend')}
                                    </Button>
                                }
                                title={t('admin.people.suspend')}
                                description={t('admin.people.suspend_body')}
                                confirmLabel={t('admin.people.suspend')}
                                action={`/admin/people/${person.id}/suspend`}
                                danger
                            />
                        )}
                    </>
                )
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Card>
                    <CardTitle>{t('admin.people.profile')}</CardTitle>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <Badge tone={person.status === 'active' ? 'success' : 'danger'}>
                            {t(`admin.status.${person.status}`)}
                        </Badge>
                        {person.ageBand === 'minor' && <Badge tone="warning">Under 18</Badge>}
                        {person.staff && <Badge tone="primary">Staff sign-in</Badge>}
                    </div>
                    <dl className="mt-4 flex flex-col gap-2 text-sm">
                        {facts.map(([label, value]) => (
                            <div key={label} className="flex justify-between gap-3">
                                <dt className="text-fg-muted">{label}</dt>
                                <dd className="text-fg text-right font-medium">{value ?? '-'}</dd>
                            </div>
                        ))}
                    </dl>
                    <p className="text-fg-muted mt-3 text-sm">{t('admin.people.devices', { count: devices })}</p>
                </Card>

                <Card className="lg:col-span-2">
                    <CardTitle>{t('admin.people.roles')}</CardTitle>
                    <ul className="mt-3 flex flex-col gap-2">
                        {roles.length === 0 && <li className="text-fg-muted text-sm">{t('profile.roles_none')}</li>}
                        {roles.map((role) => (
                            <li
                                key={role.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0"
                            >
                                <span className="text-fg text-sm">
                                    <span className="font-semibold">{role.label}</span> · {role.where}
                                </span>
                                {can.assignRoles && (can.assignNational || !role.national) && (
                                    <ReasonDialog
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                {t('admin.people.remove')}
                                            </Button>
                                        }
                                        title={t('admin.people.remove_role')}
                                        description={`${role.label} · ${role.where}`}
                                        confirmLabel={t('admin.people.remove')}
                                        action={`/admin/people/${person.id}/roles/${role.id}`}
                                        method="delete"
                                        danger
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                    {can.assignRoles && (
                        <form
                            className="border-line mt-4 grid gap-4 border-t pt-4 md:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(`/admin/people/${person.id}/roles`, {
                                    preserveScroll: true,
                                    onSuccess: () => form.reset(),
                                });
                            }}
                        >
                            <p className="text-fg text-sm font-semibold md:col-span-2">{t('admin.people.add_role')}</p>
                            <Field label={t('admin.people.role')} error={form.errors.role} required>
                                <Select
                                    value={form.data.role}
                                    onChange={(e) =>
                                        form.setData((d) => ({ ...d, role: e.target.value, scope_id: '' }))
                                    }
                                >
                                    <option value="">-</option>
                                    {available.map((r) => (
                                        <option key={r.key} value={r.key}>
                                            {r.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {needsScope && selected && (
                                <Field label={t('admin.people.where')} error={form.errors.scope_id} required>
                                    <Select
                                        value={form.data.scope_id}
                                        onChange={(e) => form.setData('scope_id', e.target.value)}
                                    >
                                        <option value="">-</option>
                                        {(scopeOptions[selected.scope] ?? []).map((o) => (
                                            <option key={o.id} value={o.id}>
                                                {o.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}
                            <Field
                                label={t('admin.reason')}
                                error={form.errors.reason}
                                required
                                className="md:col-span-2"
                            >
                                <Textarea
                                    rows={2}
                                    value={form.data.reason}
                                    onChange={(e) => form.setData('reason', e.target.value)}
                                />
                            </Field>
                            {!can.assignNational && (
                                <p className="text-fg-muted text-xs md:col-span-2">{t('admin.people.national_only')}</p>
                            )}
                            <div className="md:col-span-2">
                                <Button
                                    type="submit"
                                    loading={form.processing}
                                    disabled={!form.data.role || form.data.reason.trim().length < 5}
                                >
                                    {t('admin.people.add_role')}
                                </Button>
                            </div>
                        </form>
                    )}
                </Card>

                <Card>
                    <CardTitle>{t('admin.people.documents')}</CardTitle>
                    <ul className="mt-3 flex flex-col gap-2 text-sm">
                        {documents.length === 0 && <li className="text-fg-muted">{t('documents.none')}</li>}
                        {documents.map((d) => (
                            <li key={d.id} className="flex items-center justify-between gap-2">
                                <span className="text-fg">{t(`documents.type.${d.type}`)}</span>
                                <span className="flex items-center gap-2">
                                    <Badge tone={d.status === 'verified' ? 'success' : 'neutral'}>
                                        {t(`documents.status.${d.status}`)}
                                    </Badge>
                                    {d.url && (
                                        <a
                                            href={d.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary font-semibold hover:underline"
                                        >
                                            {t('documents.open')}
                                        </a>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card>
                    <CardTitle>{t('admin.people.consents')}</CardTitle>
                    <ul className="mt-3 flex flex-col gap-1 text-sm">
                        {consents.map((c, i) => (
                            <li key={i} className="flex justify-between gap-2">
                                <span className="text-fg">
                                    {t(`account.purpose.${c.purpose}`)} {c.granted ? '✓' : '✗'}
                                </span>
                                <span className="text-fg-muted text-xs">{formatDate(c.at)}</span>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card>
                    <CardTitle>{t('admin.people.activity')}</CardTitle>
                    <ul className="mt-3 flex flex-col gap-1 text-sm">
                        {audit.map((a, i) => (
                            <li key={i} className="flex justify-between gap-2">
                                <span className="text-fg font-mono text-xs">
                                    {a.event}
                                    {a.byStaff ? ` (${t('admin.people.by_staff')})` : ''}
                                </span>
                                <span className="text-fg-muted shrink-0 text-xs">{formatDateTime(a.at)}</span>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AdminPage>
    );
}
