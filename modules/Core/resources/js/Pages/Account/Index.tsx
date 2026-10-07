import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LogOut, Smartphone } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
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
    };
    consents: { purpose: string; granted: boolean; required: boolean }[];
    devices: { id: string; name: string; lastSeen: string | null; remembered: boolean; current: boolean }[];
    phoneChangePending: boolean;
    demoCode?: string | null;
}

function ProfileTab({ profile }: Pick<AccountProps, 'profile'>) {
    const { t, languages } = useTranslation();
    const form = useForm({
        first_name: profile.firstName,
        last_name: profile.lastName,
        preferred_name: profile.preferredName ?? '',
        preferred_locale: profile.preferredLocale,
        email: profile.email ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/account/profile', { preserveScroll: true });
    };

    return (
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
                hint={profile.email && !profile.emailVerified ? t('account.email_unverified') : t('account.email_hint')}
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
            <div className="md:col-span-2">
                <Button type="submit" loading={form.processing}>
                    {t('account.save')}
                </Button>
            </div>
        </form>
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
    const [tab] = useState(props.phoneChangePending ? 'security' : 'profile');

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
                            content: <ProfileTab profile={props.profile} />,
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
