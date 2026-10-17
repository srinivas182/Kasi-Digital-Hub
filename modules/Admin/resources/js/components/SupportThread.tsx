import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export interface SupportMessage {
    id: string;
    from_staff: boolean | number;
    internal?: boolean | number;
    body: string;
    created_at: string;
    first_name?: string | null;
}

/** The conversation on a help request; staff can add internal notes and saved replies. */
export function Thread({
    messages,
    action,
    staff,
    replies = [],
}: {
    messages: SupportMessage[];
    action: string;
    staff: boolean;
    replies?: string[];
}) {
    const { t } = useTranslation();
    const [body, setBody] = useState('');
    const [internal, setInternal] = useState(false);
    const [status, setStatus] = useState('');
    return (
        <div>
            <ul className="flex flex-col gap-2">
                {messages.map((m) => (
                    <li
                        key={m.id}
                        className={`rounded-lg p-3 text-sm ${m.internal ? 'bg-warning-soft' : m.from_staff ? 'bg-primary-soft' : 'bg-surface-muted'}`}
                    >
                        <p className="text-fg-muted text-xs">
                            {m.internal
                                ? t('support.internal')
                                : m.from_staff
                                  ? t('support.from_kasihub')
                                  : t('support.from_person')}
                            {m.first_name ? ` (${m.first_name})` : ''} · {formatDateTime(m.created_at)}
                        </p>
                        <p className="text-fg whitespace-pre-line">{m.body}</p>
                    </li>
                ))}
            </ul>
            <form
                className="mt-3 flex flex-col gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(action, staff ? { body, internal, status: status || null } : { body }, {
                        preserveScroll: true,
                        onSuccess: () => setBody(''),
                    });
                }}
            >
                {staff && replies.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {replies.map((r) => (
                            <Button key={r} type="button" size="sm" variant="ghost" onClick={() => setBody(r)}>
                                {r.slice(0, 32)}…
                            </Button>
                        ))}
                    </div>
                )}
                <label className="sr-only" htmlFor="support-body">
                    {t('support.reply')}
                </label>
                <textarea
                    id="support-body"
                    rows={3}
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    className="border-line bg-surface text-fg rounded-lg border p-2 text-sm"
                />
                {staff && (
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <label className="text-fg flex items-center gap-2">
                            <input type="checkbox" checked={internal} onChange={(e) => setInternal(e.target.checked)} />{' '}
                            {t('support.internal_note')}
                        </label>
                        <label className="text-fg flex items-center gap-2">
                            {t('support.set_status')}
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="border-line bg-surface rounded border p-1"
                            >
                                <option value="">-</option>
                                {['open', 'waiting', 'resolved', 'escalated'].map((s) => (
                                    <option key={s} value={s}>
                                        {t(`support.status.${s}`)}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                )}
                <div>
                    <Button type="submit" size="sm" disabled={!body.trim()}>
                        {t('work.messages.send')}
                    </Button>
                </div>
            </form>
        </div>
    );
}
