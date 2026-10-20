import { Head, Link, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export default function MyHelpRequests({
    tickets,
    categories,
    subject = '',
}: {
    tickets: { id: string; number: string; subject: string; status: string; created_at: string }[];
    categories: string[];
    subject?: string;
}) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const form = useForm<{ category: string; subject: string; body: string; screenshot: File | null }>({
        category: subject ? 'payments' : 'account',
        subject,
        body: '',
        screenshot: null,
    });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('support.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('support.title')}</h1>
            <p className="text-fg-muted mt-1">{t('support.lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <CardTitle>{t('support.new')}</CardTitle>
                    <form
                        className="mt-3 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/help/requests', { forceFormData: true });
                        }}
                    >
                        <Field label={t('support.category')}>
                            <Select
                                value={form.data.category}
                                onChange={(e) => form.setData('category', e.target.value)}
                            >
                                {categories.map((c) => (
                                    <option key={c} value={c}>
                                        {t(`support.cat.${c}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        {form.data.category === 'safety' && <Alert tone="warning" title={t('support.safety_note')} />}
                        <Field label={t('support.subject')} error={form.errors.subject}>
                            <Input
                                value={form.data.subject}
                                onChange={(e) => form.setData('subject', e.target.value)}
                            />
                        </Field>
                        <Field label={t('support.body')} error={form.errors.body}>
                            <Textarea
                                rows={5}
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                            />
                        </Field>
                        <Field label={t('support.screenshot')} error={form.errors.screenshot}>
                            <FileInput
                                file={form.data.screenshot}
                                onChange={(f) => form.setData('screenshot', f)}
                                chooseLabel={t('documents.file')}
                                photoLabel={t('documents.take_photo')}
                            />
                        </Field>
                        <div>
                            <Button
                                type="submit"
                                loading={form.processing}
                                disabled={!form.data.subject.trim() || form.data.body.trim().length < 10}
                            >
                                {t('support.send')}
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{t('support.mine')}</CardTitle>
                    <ul className="mt-2 flex flex-col gap-2 text-sm">
                        {tickets.map((tk) => (
                            <li key={tk.id}>
                                <Link
                                    href={`/help/requests/${tk.id}`}
                                    className="text-primary font-semibold hover:underline"
                                >
                                    {tk.number} · {tk.subject}
                                </Link>{' '}
                                <Badge>{t(`support.status.${tk.status}`)}</Badge>
                                <p className="text-fg-muted text-xs">{formatDate(tk.created_at)}</p>
                            </li>
                        ))}
                        {tickets.length === 0 && <li className="text-fg-muted">{t('support.none')}</li>}
                    </ul>
                    <p className="mt-4 text-sm">
                        <a href="/help" className="text-primary font-semibold hover:underline">
                            {t('support.browse_help')}
                        </a>
                    </p>
                </Card>
            </div>
        </AppLayout>
    );
}
