import { router, useForm } from '@inertiajs/react';

import { Badge } from '@/components/ui/display';
import { Button } from '@/components/ui/Button';
import { Field, Textarea } from '@/components/ui/form';
import { cn } from '@/lib/cn';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export interface Message {
    id: string;
    fromEmployer: boolean;
    body: string;
    held: boolean;
    mine: boolean;
    at: string;
}

/** Text-only message thread for one application (messages are checked for scams). */
export function Thread({ messages, action }: { messages: Message[]; action: string }) {
    const { t } = useTranslation();
    const form = useForm({ body: '' });

    return (
        <div>
            {messages.length === 0 && <p className="text-fg-muted text-sm">{t('work.messages.none')}</p>}
            <ol className="flex flex-col gap-2" aria-label={t('work.messages.title')}>
                {messages.map((m) => (
                    <li
                        key={m.id}
                        className={cn(
                            'max-w-[85%] rounded-lg px-3 py-2 text-sm',
                            m.mine ? 'bg-primary-soft self-end' : 'bg-surface-muted self-start',
                        )}
                    >
                        <p className="text-fg whitespace-pre-line">{m.body}</p>
                        <p className="text-fg-muted mt-1 flex items-center gap-2 text-xs">
                            {formatDateTime(m.at)}
                            {m.held && <Badge tone="warning">{t('work.messages.held_badge')}</Badge>}
                            {!m.mine && (
                                <button
                                    type="button"
                                    className="underline"
                                    onClick={() =>
                                        router.post(`/work/messages/${m.id}/report`, {}, { preserveScroll: true })
                                    }
                                >
                                    {t('work.messages.report')}
                                </button>
                            )}
                        </p>
                    </li>
                ))}
            </ol>
            <form
                className="mt-3 flex flex-col gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(action, { preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <Field label={t('work.messages.placeholder')} error={form.errors.body}>
                    <Textarea
                        rows={2}
                        maxLength={2000}
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                    />
                </Field>
                <div>
                    <Button type="submit" size="sm" loading={form.processing} disabled={!form.data.body.trim()}>
                        {t('work.messages.send')}
                    </Button>
                </div>
            </form>
            <p className="text-fg-muted mt-2 text-xs">{t('work.messages.safety')}</p>
        </div>
    );
}

export function Timeline({
    events,
}: {
    events: { kind: string; stage: string | null; at: string; meta: Record<string, unknown> }[];
}) {
    const { t } = useTranslation();
    return (
        <ol className="border-line flex flex-col gap-2 border-l-2 pl-4 text-sm">
            {events.map((e, i) => (
                <li key={i}>
                    <span className="text-fg font-medium">
                        {e.kind === 'stage'
                            ? t('work.timeline.stage', { stage: t(`work.stage.${e.stage ?? 'new'}`) })
                            : e.kind === 'interview'
                              ? t('work.timeline.interview', {
                                    status: t(`work.interview.status.${String(e.meta.status ?? 'proposed')}`),
                                })
                              : t(`work.timeline.${e.kind}`)}
                    </span>{' '}
                    <span className="text-fg-muted">{formatDateTime(e.at)}</span>
                    {typeof e.meta.message === 'string' && e.meta.message !== '' && (
                        <p className="text-fg-muted">“{e.meta.message}”</p>
                    )}
                </li>
            ))}
        </ol>
    );
}
