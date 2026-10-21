import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card } from '@/components/ui/display';
import { Field, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

/** KasiHub moderators take a business website down, or restore it after an appeal (S31). */
export default function ModerateSite({
    name,
    status,
    reason,
    url,
    business,
}: {
    name: string;
    status: string;
    reason: string | null;
    url: string;
    business: string;
}) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const form = useForm({ reason: '', restore: status === 'taken_down' });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={business} />
            <h1 className="text-fg text-2xl font-bold">{business}</h1>
            <p className="mt-1 flex flex-wrap items-center gap-2">
                <Badge tone={status === 'published' ? 'success' : 'warning'}>{t(`brand.site.status.${status}`)}</Badge>
                <a href={url} className="text-primary break-all hover:underline" rel="noopener" target="_blank">
                    {name}
                </a>
            </p>
            {reason && <p className="text-fg mt-2 text-sm">{reason}</p>}
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <Card className="mt-4 max-w-lg">
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/brand/sites/${name}/moderate`, { preserveScroll: true });
                    }}
                >
                    <Field label={t('brand.site.moderate_reason')} hint={t('brand.site.moderate_hint')}>
                        <Textarea
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                        />
                    </Field>
                    <div>
                        <Button
                            type="submit"
                            variant={form.data.restore ? 'primary' : 'danger'}
                            disabled={form.data.reason.trim().length < 5}
                            loading={form.processing}
                        >
                            {form.data.restore ? t('brand.site.restore') : t('brand.site.take_down')}
                        </Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
