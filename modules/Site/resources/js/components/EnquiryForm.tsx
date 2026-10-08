import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { useTranslation } from '@/lib/i18n';

interface EnquiryFormProps {
    kind: 'contact' | 'employer' | 'funder';
    topics?: string[];
    hubs?: { slug: string; name: string }[];
}

/** Website enquiry form with a hidden honeypot field for spam bots. */
export function EnquiryForm({ kind, topics = [], hubs = [] }: EnquiryFormProps) {
    const { t } = useTranslation();
    const { flash } = usePage().props;
    const form = useForm({
        name: '',
        organisation: '',
        phone: '',
        email: '',
        topic: topics[0] ?? '',
        hub: '',
        message: '',
        consent: false,
        website: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/enquiries/${kind}`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
            {flash.status && <Alert tone="success" title={flash.status} />}
            <Field label={t('site.form.name')} error={form.errors.name} required>
                <Input
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    autoComplete="name"
                />
            </Field>
            {kind !== 'contact' && (
                <Field label={t('site.form.organisation')} error={form.errors.organisation} required>
                    <Input
                        value={form.data.organisation}
                        onChange={(e) => form.setData('organisation', e.target.value)}
                        autoComplete="organization"
                    />
                </Field>
            )}
            <p className="text-fg-muted -mb-2 text-sm">{t('site.form.contact_hint')}</p>
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('site.form.phone')} error={form.errors.phone}>
                    <PhoneInput value="" onChange={(_, raw) => form.setData('phone', raw)} />
                </Field>
                <Field label={t('site.form.email')} error={form.errors.email}>
                    <Input
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        autoComplete="email"
                    />
                </Field>
            </div>
            {topics.length > 0 && (
                <Field label={t('site.form.topic')} error={form.errors.topic}>
                    <Select value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)}>
                        {topics.map((topic) => (
                            <option key={topic} value={topic}>
                                {t(`site.form.topic.${topic}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
            {hubs.length > 0 && (
                <Field label={t('site.form.hub')} error={form.errors.hub}>
                    <Select value={form.data.hub} onChange={(e) => form.setData('hub', e.target.value)}>
                        <option value="">{t('site.form.hub_none')}</option>
                        {hubs.map((hub) => (
                            <option key={hub.slug} value={hub.slug}>
                                {hub.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
            <Field label={t('site.form.message')} error={form.errors.message} required>
                <Textarea
                    value={form.data.message}
                    onChange={(e) => form.setData('message', e.target.value)}
                    rows={5}
                />
            </Field>
            {/* Honeypot: hidden from people and screen readers; bots fill it in. */}
            <div className="sr-only" aria-hidden>
                <label htmlFor={`website-${kind}`}>Website</label>
                <input
                    id={`website-${kind}`}
                    tabIndex={-1}
                    autoComplete="off"
                    value={form.data.website}
                    onChange={(e) => form.setData('website', e.target.value)}
                />
            </div>
            <Checkbox
                label={t('site.form.consent')}
                checked={form.data.consent}
                onCheckedChange={(c) => form.setData('consent', c === true)}
            />
            {form.errors.consent && (
                <p role="alert" className="text-danger-text -mt-3 text-sm">
                    {form.errors.consent}
                </p>
            )}
            <div>
                <Button type="submit" size="lg" loading={form.processing} disabled={!form.data.consent}>
                    {t('site.form.send')}
                </Button>
            </div>
        </form>
    );
}
