import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

/** A score badge with text (never colour alone). */
export function MatchScore({ score }: { score: number }) {
    const { t } = useTranslation();
    return (
        <span
            className={cn(
                'inline-flex min-h-7 items-center rounded-full px-3 text-sm font-bold',
                score >= 70
                    ? 'bg-kasi-green-soft text-kasi-green-ink'
                    : score >= 50
                      ? 'bg-primary-soft text-fg'
                      : 'bg-surface-muted text-fg',
            )}
        >
            {t('work.matches.score', { score })}
        </span>
    );
}
