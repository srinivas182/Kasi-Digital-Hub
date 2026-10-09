import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { RadioGroup } from '@/components/ui/RadioGroup';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

/** After scanning the door code: pick why you came. */
export default function Confirm({
    hub,
    purposes,
    alreadyToday,
}: {
    hub: { name: string };
    purposes: string[];
    alreadyToday: boolean;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const form = useForm({ purpose: 'jobs' });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('hubops.scan.title', { hub: hub.name })} />
            <div className="mx-auto max-w-lg">
                <h1 className="text-fg text-2xl font-bold">{t('hubops.scan.title', { hub: hub.name })}</h1>
                {alreadyToday && (
                    <div className="mt-4">
                        <Alert tone="info" title={t('hubops.scan.already')} />
                    </div>
                )}
                <form
                    className="mt-6 flex flex-col gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/check-in');
                    }}
                >
                    <RadioGroup
                        legend={t('hubops.scan.question')}
                        value={form.data.purpose}
                        onValueChange={(v) => form.setData('purpose', v)}
                        options={purposes.map((p) => ({ value: p, label: t(`hubops.purpose.${p}`) }))}
                    />
                    <Button type="submit" block loading={form.processing}>
                        {t('hubops.scan.confirm')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
