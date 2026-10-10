import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Card } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import type { CohortCard } from './Index';

export default function CohortJoin({
    code,
    cohort,
}: {
    code: string;
    cohort: (CohortCard & { provider: string }) | null;
}) {
    const { t } = useTranslation();
    const { auth, errors } = usePage().props;
    const form = useForm({ code, consent: false });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.cohort.join_title')} />
            <div className="mx-auto max-w-md">
                <h1 className="text-fg text-2xl font-bold">{t('learn.cohort.join_title')}</h1>
                {cohort && (
                    <Card className="mt-4">
                        <p className="text-fg font-semibold">{cohort.course}</p>
                        <p className="text-fg-muted text-sm">
                            {cohort.name} · {cohort.provider} · {cohort.hub ?? t('learn.cohort.online')}
                        </p>
                        <p className="text-fg-muted text-sm">
                            {formatDate(cohort.startsOn)} - {formatDate(cohort.endsOn)}
                        </p>
                    </Card>
                )}
                <form
                    className="mt-4 flex flex-col gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/learn/join');
                    }}
                >
                    <Field label={t('learn.cohort.join_code')} error={form.errors.code}>
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                            maxLength={8}
                            autoCapitalize="characters"
                            className="font-mono tracking-widest"
                        />
                    </Field>
                    {errors?.consent && (
                        <>
                            <Alert tone="info" title={errors.consent} />
                            <Checkbox
                                label={t('learn.enrol.consent_label')}
                                checked={form.data.consent}
                                onCheckedChange={(c) => form.setData('consent', c === true)}
                            />
                        </>
                    )}
                    <Button type="submit" loading={form.processing} disabled={form.data.code.trim().length < 4}>
                        {t('learn.cohort.join')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
