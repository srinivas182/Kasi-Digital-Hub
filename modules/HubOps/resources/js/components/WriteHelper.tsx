import { Sparkles } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Field, Textarea } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

/**
 * "Help me write it": rough notes in, an editable description out. If AI is switched off,
 * over its limit or unavailable, staff get a plain message and simply write it themselves.
 */
export function WriteHelper({ type, title, onUse }: { type: string; title: string; onUse: (text: string) => void }) {
    const { t } = useTranslation();
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);
    const [draft, setDraft] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    const write = async () => {
        setBusy(true);
        setMessage(null);
        try {
            const response = await fetch('/hub-ops/events/write', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ type, title, notes }),
            });
            const data = (await response.json()) as {
                ok?: boolean;
                description?: string;
                message?: string;
                errors?: Record<string, string[]>;
            };
            if (data.ok && data.description) setDraft(data.description);
            else setMessage(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? t('ai.fallback.unavailable'));
        } catch {
            setMessage(t('ai.fallback.unavailable'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="border-line bg-surface-muted rounded-lg border p-4">
            <p className="text-fg flex items-center gap-2 font-semibold">
                <Sparkles className="size-4" aria-hidden /> {t('hubops.events.write_title')}
            </p>
            <p className="text-fg-muted mt-1 text-sm">{t('hubops.events.write_hint')}</p>
            <p className="text-fg-muted mt-1 text-xs">{t('ai.notice')}</p>
            <Field label={t('hubops.events.write_notes')} className="mt-3">
                <Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={1500} />
            </Field>
            <Button
                type="button"
                size="sm"
                variant="secondary"
                className="mt-2"
                loading={busy}
                disabled={notes.trim().length < 10 || title.trim() === ''}
                onClick={write}
            >
                {t('hubops.events.write_go')}
            </Button>
            {message && (
                <div className="mt-3">
                    <Alert tone="info" title={message} />
                </div>
            )}
            {draft && (
                <div className="mt-3">
                    <p
                        className="border-line bg-surface text-fg rounded border p-3 text-sm whitespace-pre-line"
                        aria-live="polite"
                    >
                        {draft}
                    </p>
                    <Button type="button" size="sm" className="mt-2" onClick={() => onUse(draft)}>
                        {t('hubops.events.write_use')}
                    </Button>
                </div>
            )}
        </div>
    );
}
