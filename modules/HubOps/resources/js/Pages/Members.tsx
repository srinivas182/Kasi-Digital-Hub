import { Link, router } from '@inertiajs/react';

import { DataTable } from '@/components/ui/DataTable';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { type HubChoice, HubOpsPage, type Paginated } from '../components/HubOpsPage';

interface Member {
    id: string;
    name: string;
    phone: string;
    lastVisit: string | null;
    joined: string | null;
}

export default function Members({
    hubs,
    filter,
    members,
}: {
    hubs: HubChoice;
    filter: string;
    members: Paginated<Member>;
}) {
    const { t } = useTranslation();
    const filters = ['all', 'no_id', 'inactive'];

    return (
        <HubOpsPage title={t('hubops.members.title')} hubs={hubs} crumbs={[{ label: t('hubops.members.title') }]}>
            <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label={t('admin.filter')}>
                {filters.map((f) => (
                    <button
                        key={f}
                        type="button"
                        aria-pressed={filter === f}
                        onClick={() =>
                            router.get(
                                '/hub-ops/members',
                                { filter: f === 'all' ? undefined : f },
                                { preserveState: true },
                            )
                        }
                        className={
                            filter === f
                                ? 'bg-primary text-primary-fg rounded-full px-4 py-2 text-sm font-semibold'
                                : 'border-line text-fg hover:bg-surface-muted rounded-full border px-4 py-2 text-sm font-semibold'
                        }
                    >
                        {t(`hubops.members.filter.${f}`)}
                    </button>
                ))}
            </div>
            <DataTable<Member>
                caption={t('hubops.members.title')}
                rows={members.data}
                rowKey={(m) => m.id}
                empty={<p className="text-fg-muted">{t('admin.none')}</p>}
                columns={[
                    {
                        key: 'name',
                        header: t('admin.people.col.name'),
                        cell: (m) => <span className="font-semibold">{m.name}</span>,
                    },
                    { key: 'phone', header: t('admin.people.col.phone'), cell: (m) => m.phone },
                    {
                        key: 'last',
                        header: t('hubops.members.last_visit'),
                        cell: (m) => (m.lastVisit ? formatDate(m.lastVisit) : t('hubops.members.never')),
                    },
                    {
                        key: 'joined',
                        header: t('admin.people.col.joined'),
                        cell: (m) => (m.joined ? formatDate(m.joined) : '-'),
                        hideOnMobile: true,
                    },
                ]}
            />
            <nav aria-label="Pagination" className="mt-4 flex justify-between text-sm">
                {members.prev_page_url ? (
                    <Link href={members.prev_page_url} className="text-primary font-semibold">
                        ← Previous
                    </Link>
                ) : (
                    <span />
                )}
                {members.next_page_url && (
                    <Link href={members.next_page_url} className="text-primary font-semibold">
                        Next →
                    </Link>
                )}
            </nav>
        </HubOpsPage>
    );
}
