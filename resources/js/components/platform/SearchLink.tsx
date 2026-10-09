import { Link, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';

import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

/** Header link to the platform search (signed-in people only). */
export function SearchLink({ inverse }: { inverse?: boolean }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    if (!auth.user) return null;

    return (
        <Link
            href="/search"
            aria-label={t('search.title')}
            className={cn(
                'rounded-control inline-grid size-11 place-items-center',
                inverse ? 'text-white hover:bg-white/10' : 'text-fg hover:bg-surface-muted',
            )}
        >
            <Search className="size-5" aria-hidden />
        </Link>
    );
}
