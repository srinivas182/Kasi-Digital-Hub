import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/display';
import { SearchInput } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Group {
    type: string;
    items: { ref: string; title: string; body: string; url: string }[];
}

export default function SearchPage({ q, unavailable, groups }: { q: string; unavailable: boolean; groups: Group[] }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [query, setQuery] = useState(q);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/search', { q: query }, { preserveState: true });
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('search.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('search.title')}</h1>
            <form role="search" onSubmit={submit} className="mt-4 flex gap-2">
                <SearchInput
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={t('search.placeholder')}
                    aria-label={t('search.placeholder')}
                    className="flex-1"
                    autoFocus
                />
                <Button type="submit">{t('search.go')}</Button>
            </form>
            <div className="mt-6" aria-live="polite">
                {unavailable && <Alert tone="warning" title={t('search.unavailable')} />}
                {!unavailable && q.length >= 2 && groups.length === 0 && <EmptyState title={t('search.none', { q })} />}
                {q.length > 0 && q.length < 2 && <p className="text-fg-muted">{t('search.hint')}</p>}
                <div className="flex flex-col gap-8">
                    {groups.map((group) => (
                        <section key={group.type} aria-labelledby={`group-${group.type}`}>
                            <h2 id={`group-${group.type}`} className="text-fg text-lg font-bold">
                                {t(`search.type.${group.type}`)}
                            </h2>
                            <ul className="mt-3 grid gap-3 md:grid-cols-2">
                                {group.items.map((item) => (
                                    <li key={item.ref}>
                                        <Link href={item.url} className="block h-full">
                                            <Card className="hover:bg-surface-muted h-full">
                                                <p className="text-primary font-semibold">{item.title}</p>
                                                <p className="text-fg-muted mt-1 text-sm">{item.body}</p>
                                            </Card>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
