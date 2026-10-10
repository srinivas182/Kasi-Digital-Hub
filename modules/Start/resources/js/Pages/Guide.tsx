import { Head, Link, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

const FORMS = ['sole', 'pty', 'coop', 'npc'] as const;
const ROWS = ['who', 'liability', 'registration', 'paperwork', 'when'] as const;

/** Plain-language comparison of legal forms - guidance, not legal advice. */
export default function Guide({
    business,
}: {
    business: { id: string; name: string; legalForm: string | null; people: number };
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('start.guide.title')} />
            <Link
                href={`/start/businesses/${business.id}`}
                className="text-primary text-sm font-semibold hover:underline"
            >
                ← {business.name}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('start.guide.title')}</h1>
            <div className="mt-3 max-w-3xl">
                <Alert tone="info" title={t('start.guide.not_advice')} />
            </div>
            <ul className="mt-6 grid gap-4 md:grid-cols-2">
                {FORMS.map((f) => (
                    <li key={f}>
                        <Card className="h-full">
                            <h2 className="text-fg text-lg font-bold">{t(`start.form_name.${f}`)}</h2>
                            <dl className="mt-2 flex flex-col gap-2 text-sm">
                                {ROWS.map((r) => (
                                    <div key={r}>
                                        <dt className="text-fg-muted font-semibold">{t(`start.guide.row.${r}`)}</dt>
                                        <dd className="text-fg">{t(`start.guide.${f}.${r}`)}</dd>
                                    </div>
                                ))}
                            </dl>
                            <Button
                                className="mt-4"
                                variant={business.legalForm === f ? 'secondary' : 'primary'}
                                onClick={() => router.post(`/start/businesses/${business.id}/form`, { legal_form: f })}
                            >
                                {business.legalForm === f ? t('start.guide.chosen') : t('start.guide.choose')}
                            </Button>
                        </Card>
                    </li>
                ))}
            </ul>
        </AppLayout>
    );
}
