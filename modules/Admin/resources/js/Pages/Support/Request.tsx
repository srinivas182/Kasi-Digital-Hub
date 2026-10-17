import { Head, Link, usePage } from '@inertiajs/react';

import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type SupportMessage, Thread } from '../../components/SupportThread';

export default function HelpRequest({
    ticket,
    messages,
}: {
    ticket: { id: string; number: string; subject: string; status: string };
    messages: SupportMessage[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={ticket.number} />
            <Link href="/help/requests" className="text-primary text-sm font-semibold hover:underline">
                ← {t('support.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">
                {ticket.number} · {ticket.subject}
            </h1>
            <Badge className="mt-1">{t(`support.status.${ticket.status}`)}</Badge>
            <Card className="mt-4 max-w-3xl">
                <Thread messages={messages} action={`/help/requests/${ticket.id}`} staff={false} />
            </Card>
        </AppLayout>
    );
}
