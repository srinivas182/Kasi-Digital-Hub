import { Head, router, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card, CardTitle } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Story {
    key: string;
    persona: string;
    userId: string | null;
    steps: { url: string; notice: string }[];
}

export default function Demo({
    stories,
    personas,
}: {
    stories: Story[];
    personas: { name: string; role: string; userId: string }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const go = (userId: string | null, to: string) =>
        userId && router.post(`/demo/switch/${userId}?to=${encodeURIComponent(to)}`);
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('demo.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('demo.title')}</h1>
            <div className="mt-3">
                <Alert tone="warning" title={t('demo.warning')} />
            </div>
            <ul className="mt-6 grid gap-4 md:grid-cols-2">
                {stories.map((s) => (
                    <li key={s.key}>
                        <Card className="h-full">
                            <CardTitle>{t(`demo.story.${s.key}`)}</CardTitle>
                            <p className="text-fg-muted text-sm">{s.persona}</p>
                            <ol className="mt-3 flex list-decimal flex-col gap-2 pl-5 text-sm">
                                {s.steps.map((step) => (
                                    <li key={step.url}>
                                        <button
                                            type="button"
                                            className="text-primary text-left font-semibold hover:underline"
                                            onClick={() => go(s.userId, step.url)}
                                        >
                                            {step.url}
                                        </button>
                                        <span className="text-fg-muted block">{step.notice}</span>
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    </li>
                ))}
            </ul>
            <Card className="mt-6">
                <CardTitle>{t('demo.switch')}</CardTitle>
                <div className="mt-2 flex flex-wrap gap-2">
                    {personas.map((p) => (
                        <Button key={p.userId} size="sm" variant="secondary" onClick={() => go(p.userId, '/home')}>
                            {p.name} · {t(`roles.${p.role}`)}
                        </Button>
                    ))}
                </div>
                <div className="border-line mt-4 border-t pt-3">
                    <Button
                        variant="danger"
                        size="sm"
                        onClick={() => {
                            if (window.confirm(t('demo.reset_confirm'))) router.post('/demo/reset');
                        }}
                    >
                        {t('demo.reset')}
                    </Button>
                </div>
            </Card>
        </AppLayout>
    );
}
