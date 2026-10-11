import { useForm } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Field, Select, Textarea } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

/** "Report a concern" - on every mentoring screen. Goes only to safeguarding officers. */
export function ReportConcern({ about, mentorshipId }: { about?: string; mentorshipId?: string }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({
        about: about ?? null,
        mentorship_id: mentorshipId ?? null,
        kind: 'inappropriate',
        urgent: false,
        body: '',
    });
    if (!open) {
        return (
            <Button
                variant="ghost"
                size="sm"
                icon={<ShieldAlert className="size-4" aria-hidden />}
                onClick={() => setOpen(true)}
            >
                {t('connect.concern.report')}
            </Button>
        );
    }
    return (
        <form
            className="border-danger-text/40 flex flex-col gap-3 rounded-lg border p-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/connect/concerns', { preserveScroll: true, onSuccess: () => setOpen(false) });
            }}
        >
            <p className="text-fg text-sm">{t('connect.concern.lead')}</p>
            <Field label={t('connect.concern.kind')}>
                <Select value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>
                    {['inappropriate', 'contact', 'safety', 'other'].map((k) => (
                        <option key={k} value={k}>
                            {t(`connect.concern.kind_${k}`)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('connect.concern.what')} error={form.errors.body}>
                <Textarea rows={3} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
            </Field>
            <Checkbox
                label={t('connect.concern.urgent')}
                checked={form.data.urgent}
                onCheckedChange={(c) => form.setData('urgent', c === true)}
            />
            <div className="flex gap-2">
                <Button
                    type="submit"
                    size="sm"
                    variant="danger"
                    loading={form.processing}
                    disabled={form.data.body.trim().length < 10}
                >
                    {t('connect.concern.send')}
                </Button>
                <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>
                    {t('common.cancel')}
                </Button>
            </div>
        </form>
    );
}
