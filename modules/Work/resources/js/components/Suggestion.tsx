import { Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/Button';
import { useTranslation } from '@/lib/i18n';

/** An AI suggestion shown next to the person's own words. Nothing is used without their say-so. */
export function Suggestion({
    children,
    warnings = [],
    onUse,
    onDismiss,
}: {
    children: React.ReactNode;
    warnings?: string[];
    onUse: () => void;
    onDismiss: () => void;
}) {
    const { t } = useTranslation();
    return (
        <div className="border-primary bg-primary-soft rounded-lg border p-3" aria-live="polite">
            <p className="text-fg flex items-center gap-2 text-sm font-semibold">
                <Sparkles className="size-4" aria-hidden /> {t('work.ai.suggestion')}
            </p>
            <div className="text-fg mt-2 text-sm">{children}</div>
            {warnings.length > 0 && (
                <p className="text-danger-text mt-2 text-sm">
                    {t('work.ai.warning')} <strong>{warnings.join(', ')}</strong>
                </p>
            )}
            <div className="mt-3 flex flex-wrap gap-2">
                <Button type="button" size="sm" onClick={onUse}>
                    {t('work.ai.use')}
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={onDismiss}>
                    {t('work.ai.dismiss')}
                </Button>
            </div>
            <p className="text-fg-muted mt-2 text-xs">{t('ai.notice')}</p>
        </div>
    );
}
