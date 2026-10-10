import { Head, Link, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { BusinessForm } from '../components/BusinessForm';

interface Props {
    person: { name: string; assisted: boolean };
    businesses: { id: string; name: string; stage: string; readiness: number; next: string | null }[];
    options: { sectors: string[]; stages: string[]; forms: string[]; turnover: string[] };
    cities: { id: number; name: string }[];
}

export default function StartIndex({ person, businesses, options, cities }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('start.title')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <h1 className="text-fg text-2xl font-bold">{t('start.title')}</h1>
            <p className="text-fg-muted mt-1">{t('start.lead')}</p>
            {businesses.length > 0 && (
                <ul className="mt-6 grid gap-4 md:grid-cols-2">
                    {businesses.map((b) => (
                        <li key={b.id}>
                            <Link href={`/start/businesses/${b.id}`} className="block">
                                <Card className="hover:bg-surface-muted">
                                    <p className="text-fg text-lg font-semibold">{b.name}</p>
                                    <p className="text-fg-muted text-sm">{t(`start.stage.${b.stage}`)}</p>
                                    <div className="mt-3">
                                        <ProgressBar
                                            value={b.readiness}
                                            label={t('start.readiness', { score: b.readiness })}
                                        />
                                    </div>
                                    {b.next && (
                                        <p className="text-fg mt-2 text-sm">
                                            {t('start.step.next', { step: t(`start.next.${b.next}`) })}
                                        </p>
                                    )}
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            <Card className="mt-6 max-w-3xl">
                <CardTitle>{businesses.length ? t('start.add_another') : t('start.add_first')}</CardTitle>
                <div className="mt-4">
                    <BusinessForm
                        action="/start/businesses"
                        method="post"
                        options={options}
                        cities={cities}
                        submitLabel={t('start.create')}
                    />
                </div>
            </Card>
        </AppLayout>
    );
}
