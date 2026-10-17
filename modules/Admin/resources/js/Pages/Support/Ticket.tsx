import { Head, Link, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Badge, Card } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { type SupportMessage, Thread } from '../../components/SupportThread';

export default function SupportTicket({
    ticket,
    person,
    messages,
    replies,
}: {
    ticket: { id: string; number: string; subject: string; status: string; category: string };
    person: { id: string; name: string } | null;
    messages: SupportMessage[];
    replies: string[];
}) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={ticket.number} />
            <Link href="/admin/support" className="text-primary text-sm font-semibold hover:underline">
                ← {t('support.queue')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">
                {ticket.number} · {ticket.subject}
            </h1>
            <p className="mt-1 flex gap-1">
                <Badge>{t(`support.status.${ticket.status}`)}</Badge>
                <Badge tone="neutral">{t(`support.cat.${ticket.category}`)}</Badge>
            </p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {person && (
                <p className="mt-3 text-sm">
                    <Link
                        href={`/admin/support/people/${person.id}?reason=${encodeURIComponent(ticket.number)}`}
                        className="text-primary font-semibold hover:underline"
                    >
                        {t('support.person_summary', { name: person.name })}
                    </Link>
                </p>
            )}
            <Card className="mt-4 max-w-3xl">
                <Thread messages={messages} action={`/admin/support/${ticket.id}/reply`} staff replies={replies} />
            </Card>
        </AppLayout>
    );
}
