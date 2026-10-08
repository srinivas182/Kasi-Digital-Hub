import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FileText, LogOut, Smartphone } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/Dialog';
import { FileInput } from '@/components/ui/FileInput';
import { Badge, Card, CardTitle, EmptyState } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { Checkbox } from '@/components/ui/Checkbox';
import { Switch } from '@/components/ui/Switch';
import { Tabs } from '@/components/ui/navigation';
import { OtpInput } from '@/components/ui/OtpInput';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate, formatDateTime, formatPhone } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { DemoCode } from '../../components/DemoCode';
import { type HubOption, HubSelect } from '../../components/HubSelect';
import { PinFields } from '../../components/PinFields';

interface AccountProps {
    profile: {
        firstName: string;
        lastName: string;
        preferredName: string | null;
        phone: string;
        email: string | null;
        emailVerified: boolean;
        dateOfBirth: string;
        preferredLocale: string;
        deletionRequested: boolean;
        twoFactor: boolean;
        homeHubId: string | null;
        provinceId: number | null;
        municipalityId: number | null;
        placeName: string | null;
    };
    roles: { role: string; where: string }[];
    hubOptions: HubOption[];
    locations: { id: number; name: string; cities: { id: number; name: string }[] }[];
    consents: { purpose: string; granted: boolean; required: boolean }[];
    devices: { id: string; name: string; lastSeen: string | null; remembered: boolean; current: boolean }[];
    phoneChangePending: boolean;
    demoCode?: string | null;
    documents: DocumentItem[];
    documentTypes: string[];
    notificationPreferences: { category: string; channels: Record<string, { enabled: boolean; locked: boolean }> }[];
    whatsappOptIn: boolean;
    tab: string | null;
}

interface DocumentItem {
    id: string;
    type: string;
    name: string;
    status: string;
    reason: string | null;
    expiresOn: string | null;
    expired: boolean;
    uploadedAt: string;
    url: string | null;
    sharedWith: string[];
}

const STATUS_TONES: Record<string, 'neutral' | 'success' | 'warning' | 'danger' | 'info'> = {
    pending_scan: 'info',
    uploaded: 'neutral',
    verified: 'success',
    rejected: 'warning',
    quarantined: 'danger',
};

function DocumentsTab({ documents, documentTypes }: Pick<AccountProps, 'documents' | 'documentTypes'>) {
    const { t } = useTranslation();
    const [deleting, setDeleting] = useState<DocumentItem | null>(null);
    const form = useForm<{ type: string; file: File | null; expires_on: string }>({
        type: documentTypes[0] ?? 'other',
        file: null,
        expires_on: '',
    });

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div className="flex flex-col gap-3">
                <p className="text-fg-muted text-sm">{t('documents.description')}</p>
                {documents.length === 0 ? (
                    <EmptyState
                        icon={<FileText className="size-8" aria-hidden />}
                        title={t('documents.none')}
                        description={t('documents.none_hint')}
                    />
                ) : (
                    <ul className="flex flex-col gap-3" aria-label={t('documents.title')}>
                        {documents.map((document) => (
                            <li key={document.id}>
                                <Card className="flex flex-wrap items-center gap-4">
                                    <FileText className="text-fg-muted size-6 shrink-0" aria-hidden />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-fg font-semibold">{t(`documents.type.${document.type}`)}</p>
                                        <p className="text-fg-muted truncate text-sm">{document.name}</p>
                                        <div className="mt-1 flex flex-wrap gap-2">
                                            <Badge tone={STATUS_TONES[document.status] ?? 'neutral'}>
                                                {t(`documents.status.${document.status}`)}
                                            </Badge>
                                            {document.expiresOn && (
                                                <Badge tone={document.expired ? 'danger' : 'neutral'}>
                                                    {document.expired
                                                        ? t('documents.expired')
                                                        : t('documents.expires', {
                                                              date: formatDate(document.expiresOn),
                                                          })}
                                                </Badge>
                                            )}
                                        </div>
                                        {document.reason && (
                                            <p className="text-fg-muted mt-1 text-sm">{document.reason}</p>
                                        )}
                                        {document.sharedWith.length > 0 && (
                                            <p className="text-fg-muted mt-1 text-xs">
                                                {t('documents.shared_with', { names: document.sharedWith.join(', ') })}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-2">
                                        {document.url && (
                                            <a
                                                href={document.url}
                                                className={buttonVariants({ variant: 'secondary', size: 'sm' })}
                                            >
                                                {t('documents.open')}
                                            </a>
                                        )}
                                        <Button variant="ghost" size="sm" onClick={() => setDeleting(document)}>
                                            {t('documents.delete')}
                                        </Button>
                                    </div>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
                <ConfirmDialog
                    open={deleting !== null}
                    onOpenChange={(open) => !open && setDeleting(null)}
                    danger
                    title={t('documents.delete_confirm_title')}
                    description={t('documents.delete_confirm_body')}
                    confirmLabel={t('documents.delete')}
                    cancelLabel={t('common.cancel')}
                    onConfirm={() =>
                        deleting && router.delete(`/account/documents/${deleting.id}`, { preserveScroll: true })
                    }
                />
            </div>
            <Card>
                <CardTitle>{t('documents.upload')}</CardTitle>
                <form
                    className="mt-4 flex flex-col gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/account/documents', {
                            preserveScroll: true,
                            forceFormData: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <Field label={t('documents.type')} error={form.errors.type} required>
                        <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            {documentTypes.map((type) => (
                                <option key={type} value={type}>
                                    {t(`documents.type.${type}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field
                        label={t('documents.file')}
                        hint={t('documents.file_hint')}
                        error={form.errors.file}
                        required
                    >
                        <FileInput
                            file={form.data.file}
                            onChange={(file) => form.setData('file', file)}
                            chooseLabel={t('documents.file')}
                            photoLabel={t('documents.take_photo')}
                        />
                    </Field>
                    <Field label={t('documents.expires_on')} error={form.errors.expires_on}>
                        <Input
                            type="date"
                            value={form.data.expires_on}
                            onChange={(e) => form.setData('expires_on', e.target.value)}
                        />
                    </Field>
                    <Button type="submit" loading={form.processing} disabled={!form.data.file}>
                        {t('documents.save')}
                    </Button>
                </form>
            </Card>
        </div>
    );
}

function NotificationsTab({
    notificationPreferences,
    whatsappOptIn,
}: Pick<AccountProps, 'notificationPreferences' | 'whatsappOptIn'>) {
    const { t } = useTranslation();
    const channels = ['whatsapp', 'sms', 'email'];
    const form = useForm({
        preferences: Object.fromEntries(
            notificationPreferences.map((row) => [
                row.category,
                Object.fromEntries(channels.map((c) => [c, row.channels[c]?.enabled ?? false])),
            ]),
        ) as Record<string, Record<string, boolean>>,
    });

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put('/account/notifications', { preserveScroll: true });
            }}
        >
            <p className="text-fg-muted text-sm">{t('notifications.description')}</p>
            <Alert tone="info" title={t('notifications.quiet_hours')} />
            {!whatsappOptIn && <Alert tone="warning" title={t('notifications.whatsapp_consent')} />}
            <div className="border-line rounded-card bg-surface overflow-x-auto border">
                <table className="w-full text-sm">
                    <caption className="sr-only">{t('notifications.title')}</caption>
                    <thead className="bg-surface-muted text-fg-muted text-left text-xs font-semibold uppercase">
                        <tr>
                            <th scope="col" className="px-4 py-3">
                                &nbsp;
                            </th>
                            {channels.map((channel) => (
                                <th key={channel} scope="col" className="px-3 py-3 text-center">
                                    {t(`notifications.channel.${channel}`)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {notificationPreferences.map((row) => (
                            <tr key={row.category} className="border-line border-t">
                                <th scope="row" className="text-fg px-4 py-3 text-left font-medium">
                                    {t(`notifications.category.${row.category}`)}
                                </th>
                                {channels.map((channel) => {
                                    const locked = row.channels[channel]?.locked ?? false;
                                    const label = `${t(`notifications.category.${row.category}`)} - ${t(`notifications.channel.${channel}`)}`;
                                    return (
                                        <td key={channel} className="px-3 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                aria-label={label}
                                                className="accent-primary size-5"
                                                checked={
                                                    locked
                                                        ? true
                                                        : (form.data.preferences[row.category]?.[channel] ?? false)
                                                }
                                                disabled={locked || (channel === 'whatsapp' && !whatsappOptIn)}
                                                onChange={(e) =>
                                                    form.setData('preferences', {
                                                        ...form.data.preferences,
                                                        [row.category]: {
                                                            ...form.data.preferences[row.category],
                                                            [channel]: e.target.checked,
                                                        },
                                                    })
                                                }
                                            />
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div>
                <Button type="submit" loading={form.processing}>
                    {t('account.save')}
                </Button>
            </div>
        </form>
    );
}

function ProfileTab({
    profile,
    roles,
    hubOptions,
    locations,
}: Pick<AccountProps, 'profile' | 'roles' | 'hubOptions' | 'locations'>) {
    const { t, languages } = useTranslation();
    const form = useForm({
        first_name: profile.firstName,
        last_name: profile.lastName,
        preferred_name: profile.preferredName ?? '',
        preferred_locale: profile.preferredLocale,
        email: profile.email ?? '',
        home_hub_id: profile.homeHubId ?? '',
        province_id: profile.provinceId ? String(profile.provinceId) : '',
        municipality_id: profile.municipalityId ? String(profile.municipalityId) : '',
        place_name: profile.placeName ?? '',
    });
    const cities = locations.find((province) => String(province.id) === form.data.province_id)?.cities ?? [];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/account/profile', { preserveScroll: true });
    };

    return (
        <div className="flex flex-col gap-8">
            <form onSubmit={submit} className="grid gap-5 md:grid-cols-2">
                <Field label={t('auth.signup.first_name')} error={form.errors.first_name} required>
                    <Input value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                </Field>
                <Field label={t('auth.signup.last_name')} error={form.errors.last_name} required>
                    <Input value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                </Field>
                <Field label={t('auth.signup.preferred_name')} error={form.errors.preferred_name}>
                    <Input
                        value={form.data.preferred_name}
                        onChange={(e) => form.setData('preferred_name', e.target.value)}
                    />
                </Field>
                <Field label={t('account.language')} error={form.errors.preferred_locale}>
                    <Select
                        value={form.data.preferred_locale}
                        onChange={(e) => form.setData('preferred_locale', e.target.value)}
                    >
                        {languages.map((language) => (
                            <option key={language.code} value={language.code}>
                                {language.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field
                    label={t('account.email')}
                    hint={
                        profile.email && !profile.emailVerified
                            ? t('account.email_unverified')
                            : t('account.email_hint')
                    }
                    error={form.errors.email}
                >
                    <Input
                        type="email"
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>
                <Field label={t('account.date_of_birth')}>
                    <Input value={formatDate(profile.dateOfBirth)} disabled readOnly />
                </Field>
                <Field
                    label={t('profile.home_hub')}
                    hint={t('profile.home_hub_hint')}
                    error={form.errors.home_hub_id}
                    className="md:col-span-2"
                >
                    <HubSelect
                        hubs={hubOptions}
                        value={form.data.home_hub_id}
                        onChange={(id) => form.setData('home_hub_id', id)}
                    />
                </Field>
                <Field label={t('profile.province')} error={form.errors.province_id}>
                    <Select
                        value={form.data.province_id}
                        onChange={(e) =>
                            form.setData((data) => ({ ...data, province_id: e.target.value, municipality_id: '' }))
                        }
                    >
                        <option value="">{t('profile.choose')}</option>
                        {locations.map((province) => (
                            <option key={province.id} value={province.id}>
                                {province.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('profile.city')} error={form.errors.municipality_id}>
                    <Select
                        value={form.data.municipality_id}
                        onChange={(e) => form.setData('municipality_id', e.target.value)}
                        disabled={cities.length === 0}
                    >
                        <option value="">{t('profile.choose')}</option>
                        {cities.map((city) => (
                            <option key={city.id} value={city.id}>
                                {city.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('profile.place')} error={form.errors.place_name}>
                    <Input value={form.data.place_name} onChange={(e) => form.setData('place_name', e.target.value)} />
                </Field>
                <div className="md:col-span-2">
                    <Button type="submit" loading={form.processing}>
                        {t('account.save')}
                    </Button>
                </div>
            </form>
            <Card>
                <CardTitle>{t('profile.roles')}</CardTitle>
                <p className="text-fg-muted mt-1 text-sm">{t('profile.roles_hint')}</p>
                {roles.length === 0 ? (
                    <p className="text-fg-muted mt-3 text-sm">{t('profile.roles_none')}</p>
                ) : (
                    <ul className="mt-3 flex flex-wrap gap-2">
                        {roles.map((role) => (
                            <li key={`${role.role}-${role.where}`}>
                                <Badge tone="primary">
                                    {role.role} · {role.where}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}

function SecurityTab({
    profile,
    phoneChangePending,
    demoCode,
}: Pick<AccountProps, 'profile' | 'phoneChangePending' | 'demoCode'>) {
    const { t } = useTranslation();
    const pin = useForm({ current_pin: '', pin: '', pin_confirmation: '' });
    const phone = useForm({ phone: '' });
    const confirm = useForm({ code: '', current_pin: '' });

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardTitle>{t('account.change_pin')}</CardTitle>
                <form
                    className="mt-4 flex flex-col gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        pin.put('/account/pin', { preserveScroll: true, onFinish: () => pin.reset() });
                    }}
                >
                    <div>
                        <p className="text-fg mb-2 text-sm font-semibold">{t('account.current_pin')}</p>
                        <OtpInput
                            length={5}
                            secret
                            value={pin.data.current_pin}
                            onChange={(v) => pin.setData('current_pin', v)}
                            label={t('account.current_pin')}
                            invalid={Boolean(pin.errors.current_pin)}
                        />
                        {pin.errors.current_pin && (
                            <p role="alert" className="text-danger-text mt-2 text-sm">
                                {pin.errors.current_pin}
                            </p>
                        )}
                    </div>
                    <PinFields
                        labelKey="account.new_pin"
                        pin={pin.data.pin}
                        confirmation={pin.data.pin_confirmation}
                        onPin={(v) => pin.setData('pin', v)}
                        onConfirmation={(v) => pin.setData('pin_confirmation', v)}
                        error={pin.errors.pin}
                    />
                    <Button
                        type="submit"
                        loading={pin.processing}
                        disabled={pin.data.pin.length < 5 || pin.data.pin !== pin.data.pin_confirmation}
                    >
                        {t('account.change_pin')}
                    </Button>
                </form>
            </Card>
            <Card>
                <CardTitle>{t('account.change_phone')}</CardTitle>
                <p className="text-fg-muted mt-1 text-sm">
                    {t('account.current_phone')}:{' '}
                    <span className="text-fg font-semibold">{formatPhone(profile.phone)}</span>
                </p>
                {!phoneChangePending ? (
                    <form
                        className="mt-4 flex flex-col gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            phone.post('/account/phone', { preserveScroll: true });
                        }}
                    >
                        <Field label={t('account.new_phone')} error={phone.errors.phone} required>
                            <PhoneInput value="" onChange={(_, raw) => phone.setData('phone', raw)} />
                        </Field>
                        <Button type="submit" variant="secondary" loading={phone.processing}>
                            {t('account.send_code')}
                        </Button>
                    </form>
                ) : (
                    <form
                        className="mt-4 flex flex-col gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            confirm.post('/account/phone/verify', { preserveScroll: true });
                        }}
                    >
                        <DemoCode code={demoCode} />
                        <div>
                            <p className="text-fg mb-2 text-sm font-semibold">{t('account.phone_code')}</p>
                            <OtpInput
                                value={confirm.data.code}
                                onChange={(v) => confirm.setData('code', v)}
                                invalid={Boolean(confirm.errors.code)}
                            />
                            {confirm.errors.code && (
                                <p role="alert" className="text-danger-text mt-2 text-sm">
                                    {confirm.errors.code}
                                </p>
                            )}
                        </div>
                        <div>
                            <p className="text-fg mb-2 text-sm font-semibold">{t('account.current_pin')}</p>
                            <OtpInput
                                length={5}
                                secret
                                value={confirm.data.current_pin}
                                onChange={(v) => confirm.setData('current_pin', v)}
                                label={t('account.current_pin')}
                                invalid={Boolean(confirm.errors.current_pin)}
                            />
                            {confirm.errors.current_pin && (
                                <p role="alert" className="text-danger-text mt-2 text-sm">
                                    {confirm.errors.current_pin}
                                </p>
                            )}
                        </div>
                        <div className="flex gap-3">
                            <Button
                                variant="secondary"
                                onClick={() => router.post('/account/phone/cancel', {}, { preserveScroll: true })}
                            >
                                {t('account.cancel')}
                            </Button>
                            <Button type="submit" loading={confirm.processing}>
                                {t('account.confirm_phone')}
                            </Button>
                        </div>
                    </form>
                )}
            </Card>
        </div>
    );
}

function DevicesTab({ devices }: Pick<AccountProps, 'devices'>) {
    const { t } = useTranslation();
    return (
        <div className="flex flex-col gap-4">
            <p className="text-fg-muted text-sm">{t('account.devices_description')}</p>
            <ul className="flex flex-col gap-3">
                {devices.map((device) => (
                    <li key={device.id}>
                        <Card className="flex items-center gap-4">
                            <Smartphone className="text-fg-muted size-6 shrink-0" aria-hidden />
                            <div className="flex-1">
                                <p className="text-fg font-semibold">
                                    {device.name}{' '}
                                    {device.current && <Badge tone="success">{t('account.this_device')}</Badge>}{' '}
                                    {device.remembered && <Badge tone="primary">{t('account.remembered')}</Badge>}
                                </p>
                                {device.lastSeen && (
                                    <p className="text-fg-muted text-sm">
                                        {t('account.last_seen', { when: formatDateTime(device.lastSeen) })}
                                    </p>
                                )}
                            </div>
                            {!device.current && (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(`/account/devices/${device.id}`, { preserveScroll: true })
                                    }
                                >
                                    {t('account.sign_out_device')}
                                </Button>
                            )}
                        </Card>
                    </li>
                ))}
            </ul>
            {devices.length > 1 && (
                <div>
                    <Button
                        variant="danger"
                        onClick={() => router.post('/account/devices/sign-out-others', {}, { preserveScroll: true })}
                    >
                        {t('account.sign_out_others')}
                    </Button>
                </div>
            )}
        </div>
    );
}

function PrivacyTab({ consents }: Pick<AccountProps, 'consents'>) {
    const { t } = useTranslation();
    const form = useForm({
        consents: Object.fromEntries(consents.filter((c) => !c.required).map((c) => [c.purpose, c.granted])) as Record<
            string,
            boolean
        >,
    });

    return (
        <form
            className="flex max-w-2xl flex-col gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                form.put('/account/consents', { preserveScroll: true });
            }}
        >
            <p className="text-fg-muted mb-2 text-sm">{t('account.privacy_description')}</p>
            {consents.map((consent) => (
                <div key={consent.purpose} className="border-line border-b pb-2">
                    <Switch
                        label={t(`account.purpose.${consent.purpose}`)}
                        checked={consent.required ? true : (form.data.consents[consent.purpose] ?? false)}
                        disabled={consent.required}
                        onCheckedChange={(checked) =>
                            form.setData('consents', { ...form.data.consents, [consent.purpose]: checked })
                        }
                    />
                    <p className="text-fg-muted text-xs">{t(`account.purpose.${consent.purpose}_hint`)}</p>
                </div>
            ))}
            <div className="mt-4">
                <Button type="submit" loading={form.processing}>
                    {t('account.save')}
                </Button>
            </div>
        </form>
    );
}

function DeleteTab({ profile }: Pick<AccountProps, 'profile'>) {
    const { t } = useTranslation();
    const form = useForm({ confirm: false });

    if (profile.deletionRequested) {
        return <Alert tone="warning" title={t('account.deletion_pending')} />;
    }

    return (
        <form
            className="flex max-w-xl flex-col gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/account/deletion', { preserveScroll: true });
            }}
        >
            <p className="text-fg">{t('account.delete_description')}</p>
            <Checkbox
                label={t('account.delete_confirm')}
                checked={form.data.confirm}
                onCheckedChange={(c) => form.setData('confirm', c === true)}
            />
            <div>
                <Button type="submit" variant="danger" disabled={!form.data.confirm} loading={form.processing}>
                    {t('account.delete_request')}
                </Button>
            </div>
        </form>
    );
}

export default function AccountIndex(props: AccountProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const [tab] = useState(props.tab ?? (props.phoneChangePending ? 'security' : 'profile'));

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('account.title')} />
            <div className="flex items-center justify-between gap-4">
                <h1 className="text-fg text-2xl font-bold tracking-tight">{t('account.title')}</h1>
                <Button
                    variant="ghost"
                    icon={<LogOut className="size-4" aria-hidden />}
                    onClick={() => router.post('/logout')}
                >
                    {t('account.sign_out')}
                </Button>
            </div>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6">
                <Tabs
                    label={t('account.title')}
                    defaultValue={tab}
                    items={[
                        {
                            value: 'profile',
                            label: t('account.tab_profile'),
                            content: (
                                <ProfileTab
                                    profile={props.profile}
                                    roles={props.roles}
                                    hubOptions={props.hubOptions}
                                    locations={props.locations}
                                />
                            ),
                        },
                        {
                            value: 'security',
                            label: t('account.tab_security'),
                            content: (
                                <SecurityTab
                                    profile={props.profile}
                                    phoneChangePending={props.phoneChangePending}
                                    demoCode={props.demoCode}
                                />
                            ),
                        },
                        {
                            value: 'documents',
                            label: t('account.tab_documents'),
                            content: <DocumentsTab documents={props.documents} documentTypes={props.documentTypes} />,
                        },
                        {
                            value: 'notifications',
                            label: t('account.tab_notifications'),
                            content: (
                                <NotificationsTab
                                    notificationPreferences={props.notificationPreferences}
                                    whatsappOptIn={props.whatsappOptIn}
                                />
                            ),
                        },
                        {
                            value: 'devices',
                            label: t('account.tab_devices'),
                            content: <DevicesTab devices={props.devices} />,
                        },
                        {
                            value: 'privacy',
                            label: t('account.tab_privacy'),
                            content: <PrivacyTab consents={props.consents} />,
                        },
                        {
                            value: 'delete',
                            label: t('account.tab_delete'),
                            content: <DeleteTab profile={props.profile} />,
                        },
                    ]}
                />
            </div>
        </AppLayout>
    );
}
