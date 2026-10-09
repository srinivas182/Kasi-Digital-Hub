import { Link, router, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle, EmptyState } from '@/components/ui/display';
import { Field, SearchInput, Select } from '@/components/ui/form';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { type HubChoice, HubOpsPage } from '../components/HubOpsPage';

interface Person {
    id: string;
    name: string;
    phone: string;
    homeHub: string | null;
    here: boolean;
    minor: boolean;
}

interface Visit {
    id: string;
    name: string | null;
    personId: string | null;
    purpose: string;
    method: string;
    at: string;
}

interface Props {
    hubs: HubChoice;
    q: string;
    results: Person[];
    today: Visit[];
    purposes: string[];
}

/** Front desk: find a person (or count a walk-in), check them in, start helping them. */
export default function CheckIn({ hubs, q, results, today, purposes }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(q);
    const [purpose, setPurpose] = useState('jobs');
    const form = useForm({ person_id: '' as string | null, purpose: 'jobs' });

    const checkIn = (personId: string | null) => {
        form.transform(() => ({ person_id: personId, purpose }));
        form.post('/hub-ops/check-in', { preserveScroll: true });
    };
    const find = (event: FormEvent) => {
        event.preventDefault();
        router.get('/hub-ops/check-in', { q: search }, { preserveState: true, preserveScroll: true });
    };

    return (
        <HubOpsPage
            title={t('hubops.checkin.title')}
            hubs={hubs}
            crumbs={[{ label: t('hubops.checkin.title') }]}
            actions={
                <Link href="/hub-ops/register" className={buttonVariants({})}>
                    <UserPlus className="size-4" aria-hidden /> {t('hubops.checkin.register')}
                </Link>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <Card>
                    <form onSubmit={find} className="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <Field
                            label={t('hubops.checkin.search')}
                            hint={t('hubops.checkin.search_hint')}
                            className="flex-1"
                        >
                            <SearchInput
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                aria-label={t('hubops.checkin.search')}
                            />
                        </Field>
                        <Field label={t('hubops.checkin.purpose')}>
                            <Select value={purpose} onChange={(e) => setPurpose(e.target.value)}>
                                {purposes.map((p) => (
                                    <option key={p} value={p}>
                                        {t(`hubops.purpose.${p}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Button type="submit" variant="secondary">
                            {t('admin.search')}
                        </Button>
                    </form>

                    {q.length >= 3 && (
                        <ul className="mt-4 flex flex-col gap-2" aria-label={t('hubops.checkin.search')}>
                            {results.length === 0 && (
                                <li className="text-fg-muted text-sm">{t('hubops.checkin.no_results')}</li>
                            )}
                            {results.map((p) => (
                                <li
                                    key={p.id}
                                    className="border-line flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <span>
                                        <span className="text-fg block font-semibold">{p.name}</span>
                                        <span className="text-fg-muted block text-sm">
                                            {p.phone}
                                            {p.homeHub ? ` · ${p.homeHub}` : ''}
                                        </span>
                                        <span className="mt-1 flex gap-2">
                                            {p.here && <Badge tone="success">{t('hubops.checkin.here')}</Badge>}
                                            {p.minor && <Badge tone="warning">{t('hubops.checkin.minor')}</Badge>}
                                        </span>
                                    </span>
                                    <span className="flex flex-wrap gap-2">
                                        {!p.here && (
                                            <Button size="sm" onClick={() => checkIn(p.id)} loading={form.processing}>
                                                {t('hubops.checkin.check_in')}
                                            </Button>
                                        )}
                                        {p.here && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => router.post(`/hub-ops/assist/${p.id}`)}
                                            >
                                                {t('hubops.checkin.help')}
                                            </Button>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="border-line mt-6 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <span>
                            <span className="text-fg block font-semibold">{t('hubops.checkin.walk_in')}</span>
                            <span className="text-fg-muted text-sm">{t('hubops.checkin.walk_in_hint')}</span>
                        </span>
                        <Button variant="secondary" onClick={() => checkIn(null)} loading={form.processing}>
                            {t('hubops.checkin.check_in')}
                        </Button>
                    </div>
                </Card>

                <Card>
                    <CardTitle>{t('hubops.checkin.today')}</CardTitle>
                    {today.length === 0 ? (
                        <EmptyState title={t('hubops.checkin.none_today')} />
                    ) : (
                        <ul className="mt-3 flex flex-col gap-2 text-sm">
                            {today.map((v) => (
                                <li key={v.id} className="flex justify-between gap-2">
                                    <span className="text-fg">{v.name ?? t('hubops.method.walk_in')}</span>
                                    <span className="text-fg-muted shrink-0">
                                        {t(`hubops.purpose.${v.purpose}`)} · {formatDateTime(v.at).split(', ').pop()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </HubOpsPage>
    );
}
