import { Link, router } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

import { useTranslation } from '@/lib/i18n';

export interface EligibilityResult {
    eligible: boolean;
    met: number;
    total: number;
    checks: { key: string; met: boolean; params: Record<string, string | number>; step: string | null }[];
}

/** Each requirement: met or not, with a link to the KasiStart step that fixes it. */
export function EligibilityList({ result, businessId }: { result: EligibilityResult; businessId: string | null }) {
    const { t } = useTranslation();
    if (result.total === 0) return <p className="text-fg text-sm">{t('partner.elig.open_to_all')}</p>;
    return (
        <ul className="flex flex-col gap-1 text-sm">
            {result.checks.map((c, i) => (
                <li key={i} className="flex items-start gap-2">
                    {c.met ? (
                        <CheckCircle2
                            className="text-success-text mt-0.5 size-4 shrink-0"
                            aria-label={t('partner.elig.met')}
                        />
                    ) : (
                        <XCircle
                            className="text-danger-text mt-0.5 size-4 shrink-0"
                            aria-label={t('partner.elig.not_met')}
                        />
                    )}
                    <span className="text-fg">
                        {t(`partner.elig.${c.key}`, {
                            ...c.params,
                            step: c.params.step ? t(`start.next.${c.params.step}`) : '',
                        })}
                        {!c.met && c.step && businessId && (
                            <>
                                {' '}
                                <Link
                                    href={`/start/businesses/${businessId}`}
                                    className="text-primary font-semibold hover:underline"
                                >
                                    {t('partner.elig.fix')}
                                </Link>
                            </>
                        )}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export function Thread({
    messages,
    action,
}: {
    messages: { id: string; fromEmployer: boolean; body: string; held: boolean; mine: boolean; at: string }[];
    action: string | null;
}) {
    const { t } = useTranslation();
    return (
        <div>
            <ul className="flex flex-col gap-2">
                {messages.map((m) => (
                    <li
                        key={m.id}
                        className={`rounded-lg p-3 text-sm ${m.mine ? 'bg-primary-soft self-end' : 'bg-surface-muted self-start'}`}
                    >
                        <p className="text-fg whitespace-pre-line">{m.body}</p>
                        {m.held && <p className="text-warning-text text-xs">{t('work.messages.held_badge')}</p>}
                    </li>
                ))}
                {messages.length === 0 && <li className="text-fg-muted text-sm">{t('partner.messages.none')}</li>}
            </ul>
            {action && (
                <form
                    className="mt-3 flex gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const input = e.currentTarget.elements.namedItem('body') as HTMLTextAreaElement;
                        if (!input.value.trim()) return;
                        router.post(
                            action,
                            { body: input.value },
                            { preserveScroll: true, onSuccess: () => (input.value = '') },
                        );
                    }}
                >
                    <label className="sr-only" htmlFor="message-body">
                        {t('partner.messages.write')}
                    </label>
                    <textarea
                        id="message-body"
                        name="body"
                        rows={2}
                        maxLength={2000}
                        className="border-line bg-surface text-fg flex-1 rounded-lg border p-2 text-sm"
                        placeholder={t('partner.messages.write')}
                    />
                    <button
                        type="submit"
                        className="bg-primary text-primary-fg min-h-11 rounded-lg px-4 text-sm font-semibold"
                    >
                        {t('work.messages.send')}
                    </button>
                </form>
            )}
        </div>
    );
}
