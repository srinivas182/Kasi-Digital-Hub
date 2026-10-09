import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Field, Textarea } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

interface ReasonDialogProps {
    trigger: ReactNode;
    title: string;
    description?: string;
    confirmLabel: string;
    action: string;
    method?: 'post' | 'put' | 'delete';
    /** Extra fields sent with the reason. */
    extra?: Record<string, string | number | boolean | null>;
    danger?: boolean;
}

/** Sensitive actions need a written reason (stored in the audit log). */
export function ReasonDialog({
    trigger,
    title,
    description,
    confirmLabel,
    action,
    method = 'post',
    danger,
    extra,
}: ReasonDialogProps) {
    const { t } = useTranslation();
    const form = useForm({ reason: '' });

    return (
        <Dialog
            trigger={trigger}
            title={title}
            description={description}
            footer={
                <Button
                    variant={danger ? 'danger' : 'primary'}
                    loading={form.processing}
                    disabled={form.data.reason.trim().length < 5}
                    onClick={() => {
                        form.transform((data) => ({ ...extra, ...data }));
                        form.submit(method, action, { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    {confirmLabel}
                </Button>
            }
        >
            <Field label={t('admin.reason')} hint={t('admin.reason_hint')} error={form.errors.reason} required>
                <Textarea value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} rows={3} />
            </Field>
        </Dialog>
    );
}
