import { useForm, usePage } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button, IconButton } from '@/components/ui/Button';
import { Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { useTranslation } from '@/lib/i18n';

import { type HubChoice, HubOpsPage } from '../components/HubOpsPage';
import { ReasonDialog } from '../components/ReasonDialog';

interface Props {
    hubs: HubChoice;
    hub: {
        id: string;
        slug: string | null;
        description: string | null;
        phone: string | null;
        email: string | null;
        openingHours: Record<string, string>;
        trustedIps: string[];
        kioskConfigured: boolean;
    };
    kioskUrl: string | null;
    currentIp: string;
    facilitators: { id: string; name: string | null; phone: string | null }[];
}

export default function Settings({ hubs, hub, kioskUrl, currentIp, facilitators }: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const form = useForm({
        description: hub.description ?? '',
        phone: hub.phone ?? '',
        email: hub.email ?? '',
        opening_hours: { 'mon-fri': hub.openingHours['mon-fri'] ?? '', sat: hub.openingHours.sat ?? '' },
        trusted_ips: hub.trustedIps,
    });
    const kiosk = useForm({});
    const appoint = useForm({ phone: '', reason: '' });
    const ipErrors = Object.entries(form.errors).filter(([key]) => key.startsWith('trusted_ips'));

    return (
        <HubOpsPage title={t('hubops.settings.title')} hubs={hubs} crumbs={[{ label: t('hubops.settings.title') }]}>
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('hubops.settings.public')}</CardTitle>
                    <form
                        className="mt-4 flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put('/hub-ops/settings', { preserveScroll: true });
                        }}
                    >
                        <Field label={t('admin.hubs.field.description')} error={form.errors.description}>
                            <Textarea
                                rows={3}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <Field label={t('admin.hubs.field.phone')} error={form.errors.phone}>
                            <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label={t('admin.hubs.field.email')} error={form.errors.email}>
                            <Input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label={t('hubops.settings.hours_week')}>
                            <Input
                                value={form.data.opening_hours['mon-fri']}
                                onChange={(e) =>
                                    form.setData('opening_hours', {
                                        ...form.data.opening_hours,
                                        'mon-fri': e.target.value,
                                    })
                                }
                            />
                        </Field>
                        <Field label={t('hubops.settings.hours_sat')}>
                            <Input
                                value={form.data.opening_hours.sat}
                                onChange={(e) =>
                                    form.setData('opening_hours', { ...form.data.opening_hours, sat: e.target.value })
                                }
                            />
                        </Field>

                        <fieldset className="border-line border-t pt-4">
                            <legend className="text-fg text-sm font-semibold">{t('hubops.settings.connection')}</legend>
                            <p className="text-fg-muted mb-3 text-sm">
                                {t('hubops.settings.connection_hint', { ip: currentIp })}
                            </p>
                            <ul className="flex flex-col gap-2">
                                {form.data.trusted_ips.map((ip, i) => (
                                    <li key={i} className="flex items-center gap-2">
                                        <Input
                                            aria-label={t('hubops.settings.connection')}
                                            value={ip}
                                            onChange={(e) =>
                                                form.setData(
                                                    'trusted_ips',
                                                    form.data.trusted_ips.map((v, j) => (j === i ? e.target.value : v)),
                                                )
                                            }
                                        />
                                        <IconButton
                                            label={t('hubops.settings.remove')}
                                            onClick={() =>
                                                form.setData(
                                                    'trusted_ips',
                                                    form.data.trusted_ips.filter((_, j) => j !== i),
                                                )
                                            }
                                        >
                                            <X className="size-4" aria-hidden />
                                        </IconButton>
                                    </li>
                                ))}
                            </ul>
                            {ipErrors.map(([key, message]) => (
                                <p key={key} className="text-danger-text mt-1 text-sm">
                                    {message}
                                </p>
                            ))}
                            <div className="mt-2 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    icon={<Plus className="size-4" aria-hidden />}
                                    onClick={() => form.setData('trusted_ips', [...form.data.trusted_ips, ''])}
                                >
                                    {t('hubops.settings.add_ip')}
                                </Button>
                                {!form.data.trusted_ips.includes(currentIp) && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            form.setData('trusted_ips', [...form.data.trusted_ips, currentIp])
                                        }
                                    >
                                        {t('hubops.settings.use_current')}
                                    </Button>
                                )}
                            </div>
                        </fieldset>
                        <div>
                            <Button type="submit" loading={form.processing}>
                                {t('account.save')}
                            </Button>
                        </div>
                    </form>
                </Card>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('hubops.settings.door')}</CardTitle>
                        <p className="text-fg-muted mt-2 text-sm">{t('hubops.settings.door_hint')}</p>
                        <p className="text-fg mt-2 text-sm">
                            {hub.kioskConfigured ? t('hubops.settings.door_set') : t('hubops.settings.door_none')}
                        </p>
                        {kioskUrl && (
                            <div className="mt-3">
                                <Alert tone="warning" title={t('hubops.settings.door_link')}>
                                    <code className="break-all">{kioskUrl}</code>
                                </Alert>
                            </div>
                        )}
                        <Button
                            className="mt-4"
                            variant="secondary"
                            loading={kiosk.processing}
                            onClick={() => kiosk.post('/hub-ops/settings/kiosk', { preserveScroll: true })}
                        >
                            {t('hubops.settings.door_new')}
                        </Button>
                    </Card>

                    <Card>
                        <CardTitle>{t('hubops.settings.facilitators')}</CardTitle>
                        <ul className="mt-3 flex flex-col gap-2 text-sm">
                            {facilitators.length === 0 && <li className="text-fg-muted">{t('admin.none')}</li>}
                            {facilitators.map((f) => (
                                <li key={f.id} className="flex items-center justify-between gap-2">
                                    <span>
                                        <span className="text-fg font-semibold">{f.name}</span>{' '}
                                        <span className="text-fg-muted">{f.phone}</span>
                                    </span>
                                    <ReasonDialog
                                        trigger={
                                            <Button size="sm" variant="ghost">
                                                {t('hubops.settings.remove')}
                                            </Button>
                                        }
                                        title={t('hubops.settings.remove_title')}
                                        description={f.name ?? undefined}
                                        confirmLabel={t('hubops.settings.remove')}
                                        action={`/hub-ops/settings/facilitators/${f.id}`}
                                        method="delete"
                                        danger
                                    />
                                </li>
                            ))}
                        </ul>
                        <form
                            className="border-line mt-4 flex flex-col gap-3 border-t pt-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                appoint.post('/hub-ops/settings/facilitators', {
                                    preserveScroll: true,
                                    onSuccess: () => appoint.reset(),
                                });
                            }}
                        >
                            <Field label={t('hubops.settings.appoint')} error={appoint.errors.phone ?? errors?.phone}>
                                <PhoneInput
                                    value={appoint.data.phone}
                                    onChange={(_, raw) => appoint.setData('phone', raw)}
                                />
                            </Field>
                            <Field label={t('admin.reason')} error={appoint.errors.reason}>
                                <Input
                                    value={appoint.data.reason}
                                    onChange={(e) => appoint.setData('reason', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    loading={appoint.processing}
                                    disabled={appoint.data.reason.trim().length < 5}
                                >
                                    {t('hubops.settings.appoint')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </HubOpsPage>
    );
}
