import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card } from '@/components/ui/display';
import { Field, Input, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Step {
    id: number;
    key: string;
    title: string;
    summary: string;
    why: string | null;
    needs: string[] | null;
    where: string | null;
    link: string | null;
    cost_note: string | null;
    duration: string | null;
    applies_to: { forms?: string[]; sectors?: string[]; employees?: boolean | null };
    document_type: string | null;
    active: boolean;
    lastChecked: string | null;
}

function StepEditor({ step }: { step: Step }) {
    const { t } = useTranslation();
    const form = useForm({
        title: step.title,
        summary: step.summary,
        why: step.why ?? '',
        needs: (step.needs ?? []).join('\n'),
        where: step.where ?? '',
        link: step.link ?? '',
        cost_note: step.cost_note ?? '',
        duration: step.duration ?? '',
        active: step.active,
        checked: false,
    });
    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-fg font-mono text-sm">{step.key}</span>
                <span className="text-fg-muted text-xs">
                    {step.lastChecked ? (
                        t('start.steps.checked', { date: formatDate(step.lastChecked) })
                    ) : (
                        <Badge tone="warning">{t('start.admin.never_checked')}</Badge>
                    )}
                </span>
            </div>
            <form
                className="mt-3 grid gap-3 md:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((d) => ({
                        ...d,
                        needs: d.needs
                            .split('\n')
                            .map((n) => n.trim())
                            .filter(Boolean),
                        link: d.link || null,
                        forms: step.applies_to.forms ?? ['*'],
                        sectors: step.applies_to.sectors ?? ['*'],
                        employees: step.applies_to.employees ?? null,
                        document_type: step.document_type,
                    }));
                    form.put(`/start/admin/steps/${step.id}`, { preserveScroll: true });
                }}
            >
                <Field label={t('start.admin.title')} error={form.errors.title} className="md:col-span-2">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label={t('start.admin.summary')} error={form.errors.summary} className="md:col-span-2">
                    <Textarea
                        rows={3}
                        value={form.data.summary}
                        onChange={(e) => form.setData('summary', e.target.value)}
                    />
                </Field>
                <Field label={t('start.admin.why')} className="md:col-span-2">
                    <Textarea rows={2} value={form.data.why} onChange={(e) => form.setData('why', e.target.value)} />
                </Field>
                <Field label={t('start.admin.needs')} hint={t('start.admin.needs_hint')}>
                    <Textarea
                        rows={3}
                        value={form.data.needs}
                        onChange={(e) => form.setData('needs', e.target.value)}
                    />
                </Field>
                <Field label={t('start.admin.where')}>
                    <Textarea
                        rows={3}
                        value={form.data.where}
                        onChange={(e) => form.setData('where', e.target.value)}
                    />
                </Field>
                <Field label={t('start.admin.link')} error={form.errors.link}>
                    <Input value={form.data.link} onChange={(e) => form.setData('link', e.target.value)} />
                </Field>
                <Field label={t('start.admin.cost')}>
                    <Input value={form.data.cost_note} onChange={(e) => form.setData('cost_note', e.target.value)} />
                </Field>
                <Field label={t('start.admin.duration')}>
                    <Input value={form.data.duration} onChange={(e) => form.setData('duration', e.target.value)} />
                </Field>
                <div className="flex flex-col gap-1">
                    <Checkbox
                        label={t('start.admin.active')}
                        checked={form.data.active}
                        onCheckedChange={(c) => form.setData('active', c === true)}
                    />
                    <Checkbox
                        label={t('start.admin.checked')}
                        checked={form.data.checked}
                        onCheckedChange={(c) => form.setData('checked', c === true)}
                    />
                </div>
                <div>
                    <Button type="submit" size="sm" loading={form.processing}>
                        {t('work.save')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

export default function AdminSteps({ steps }: { steps: Step[] }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('start.admin.page')} />
            <h1 className="text-fg text-2xl font-bold">{t('start.admin.page')}</h1>
            <p className="text-fg-muted mt-1 text-sm">{t('start.admin.lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 flex flex-col gap-4">
                {steps.map((s) => (
                    <StepEditor key={s.id} step={s} />
                ))}
            </div>
        </AppLayout>
    );
}
