import { useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

import { type HubChoice, HubOpsPage } from '../../components/HubOpsPage';
import { WriteHelper } from '../../components/WriteHelper';

interface Props {
    hubs: HubChoice;
    event: {
        id: string;
        type: string;
        title: string;
        description: string | null;
        room: string | null;
        startsAt: string;
        endsAt: string;
        capacity: number;
        audience: string;
    } | null;
    types: string[];
    audiences: string[];
    aiEnabled?: boolean;
}

export default function EventEdit({ hubs, event, types, audiences, aiEnabled = false }: Props) {
    const { t } = useTranslation();
    const form = useForm({
        type: event?.type ?? 'workshop',
        title: event?.title ?? '',
        description: event?.description ?? '',
        room: event?.room ?? '',
        starts_at: event?.startsAt ?? '',
        ends_at: event?.endsAt ?? '',
        capacity: String(event?.capacity ?? 20),
        audience: event?.audience ?? 'public',
    });
    const title = event?.title ?? t('hubops.events.new');

    return (
        <HubOpsPage
            title={title}
            hubs={event ? undefined : hubs}
            crumbs={[{ label: t('hubops.events.title'), href: '/hub-ops/events' }, { label: title }]}
        >
            <Card className="max-w-3xl">
                <form
                    className="grid gap-4 md:grid-cols-2"
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (event) form.put(`/hub-ops/events/${event.id}`);
                        else form.post('/hub-ops/events');
                    }}
                >
                    <Field
                        label={t('hubops.events.field.title')}
                        error={form.errors.title}
                        required
                        className="md:col-span-2"
                    >
                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    </Field>
                    <Field label={t('hubops.events.field.type')} error={form.errors.type}>
                        <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            {types.map((type) => (
                                <option key={type} value={type}>
                                    {t(`hubops.events.type.${type}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('hubops.events.field.audience')} error={form.errors.audience}>
                        <Select value={form.data.audience} onChange={(e) => form.setData('audience', e.target.value)}>
                            {audiences.map((a) => (
                                <option key={a} value={a}>
                                    {t(`hubops.events.audience.${a}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('hubops.events.field.starts')} error={form.errors.starts_at} required>
                        <Input
                            type="datetime-local"
                            value={form.data.starts_at}
                            onChange={(e) => form.setData('starts_at', e.target.value)}
                        />
                    </Field>
                    <Field label={t('hubops.events.field.ends')} error={form.errors.ends_at} required>
                        <Input
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(e) => form.setData('ends_at', e.target.value)}
                        />
                    </Field>
                    <Field label={t('hubops.events.field.room')} error={form.errors.room}>
                        <Input value={form.data.room} onChange={(e) => form.setData('room', e.target.value)} />
                    </Field>
                    <Field label={t('hubops.events.field.capacity')} error={form.errors.capacity} required>
                        <Input
                            type="number"
                            min={1}
                            value={form.data.capacity}
                            onChange={(e) => form.setData('capacity', e.target.value)}
                        />
                    </Field>
                    {aiEnabled && (
                        <div className="md:col-span-2">
                            <WriteHelper
                                type={form.data.type}
                                title={form.data.title}
                                onUse={(text) => form.setData('description', text)}
                            />
                        </div>
                    )}
                    <Field
                        label={t('hubops.events.field.description')}
                        error={form.errors.description}
                        className="md:col-span-2"
                    >
                        <Textarea
                            rows={4}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </Field>
                    <div className="md:col-span-2">
                        <Button type="submit" loading={form.processing}>
                            {t('account.save')}
                        </Button>
                    </div>
                </form>
            </Card>
        </HubOpsPage>
    );
}
