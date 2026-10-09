import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Listing {
    id: string;
    title: string;
    status: string;
    reason: string | null;
    closesOn: string;
    views: number;
    saves: number;
}

interface Props {
    person: { name: string; assisted: boolean };
    employer: {
        id: string;
        name: string;
        tradingName: string | null;
        status: string;
        community: boolean;
        sector: string | null;
        sizeBand: string | null;
        description: string | null;
        website: string | null;
        email: string | null;
    };
    employers: { id: string; name: string }[];
    isAdmin: boolean;
    listings: Listing[];
    team: { userId: string; name: string | null; phone: string | null; role: string }[];
    limit: number;
    options: { sectors: string[]; sizes: string[] };
}

const TONE: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    live: 'success',
    review: 'warning',
    draft: 'neutral',
    taken_down: 'danger',
    expired: 'neutral',
    closed: 'neutral',
    filled: 'success',
};

export default function Dashboard({ person, employer, employers, isAdmin, listings, team, limit, options }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const profile = useForm({
        trading_name: employer.tradingName ?? '',
        description: employer.description ?? '',
        website: employer.website ?? '',
        contact_email: employer.email ?? '',
        sector: employer.sector ?? 'other',
        size_band: employer.sizeBand ?? '1',
    });
    const member = useForm({ phone: '' });
    const active = listings.filter((l) => l.status === 'live' || l.status === 'review').length;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('work.employer.dashboard')} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{employer.tradingName ?? employer.name}</h1>
                    <div className="mt-1 flex flex-wrap gap-2">
                        <Badge
                            tone={
                                employer.status === 'verified'
                                    ? 'success'
                                    : employer.status === 'pending'
                                      ? 'warning'
                                      : 'danger'
                            }
                        >
                            {t(`work.employer.status.${employer.status}`)}
                        </Badge>
                        {employer.community && <Badge>{t('work.employer.community_badge')}</Badge>}
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {employers.length > 1 && (
                        <Select
                            aria-label={t('work.employer.dashboard')}
                            value={employer.id}
                            onChange={(e) => router.get('/work/employer', { employer: e.target.value })}
                        >
                            {employers.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.name}
                                </option>
                            ))}
                        </Select>
                    )}
                    <Link href="/work/employer/listings/create" className={buttonVariants({})}>
                        <Plus className="size-4" aria-hidden /> {t('work.employer.new_listing')}
                    </Link>
                </div>
            </div>
            {employer.status === 'pending' && (
                <div className="mt-4">
                    <Alert tone="info" title={t('work.employer.pending_hint')} />
                </div>
            )}
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <section className="mt-6" aria-labelledby="listings">
                <div className="flex items-center justify-between">
                    <h2 id="listings" className="text-fg text-lg font-bold">
                        {t('work.employer.listings')}
                    </h2>
                    <span className="text-fg-muted text-sm">{t('work.employer.active', { count: active, limit })}</span>
                </div>
                <div className="mt-3">
                    <DataTable<Listing>
                        caption={t('work.employer.listings')}
                        rows={listings}
                        rowKey={(l) => l.id}
                        empty={<p className="text-fg-muted">{t('work.employer.no_listings')}</p>}
                        columns={[
                            {
                                key: 'title',
                                header: t('work.listing.title'),
                                cell: (l) => (
                                    <span>
                                        <Link
                                            href={`/work/employer/listings/${l.id}/edit`}
                                            className="text-primary font-semibold hover:underline"
                                        >
                                            {l.title}
                                        </Link>
                                        {l.reason && <span className="text-fg-muted block text-xs">{l.reason}</span>}
                                    </span>
                                ),
                            },
                            {
                                key: 'status',
                                header: t('admin.people.col.status'),
                                cell: (l) => (
                                    <Badge tone={TONE[l.status] ?? 'neutral'}>
                                        {t(`work.listing.status.${l.status}`)}
                                    </Badge>
                                ),
                            },
                            {
                                key: 'closes',
                                header: t('work.employer.closes'),
                                cell: (l) => formatDate(l.closesOn),
                                hideOnMobile: true,
                            },
                            { key: 'views', header: t('work.employer.views'), cell: (l) => l.views },
                            {
                                key: 'saves',
                                header: t('work.employer.saves'),
                                cell: (l) => l.saves,
                                hideOnMobile: true,
                            },
                            {
                                key: 'candidates',
                                header: t('work.candidates.link'),
                                cell: (l) =>
                                    l.status === 'live' && employer.status === 'verified' ? (
                                        <span className="flex flex-col">
                                            <Link
                                                href={`/work/employer/listings/${l.id}/applicants`}
                                                className="text-primary font-semibold hover:underline"
                                            >
                                                {t('work.pipeline.link')}
                                            </Link>
                                            <Link
                                                href={`/work/employer/listings/${l.id}/candidates`}
                                                className="text-primary font-semibold hover:underline"
                                            >
                                                {t('work.candidates.title')}
                                            </Link>
                                        </span>
                                    ) : (
                                        '-'
                                    ),
                            },
                        ]}
                    />
                </div>
            </section>
            {isAdmin && (
                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardTitle>{t('work.employer.profile')}</CardTitle>
                        <form
                            className="mt-4 flex flex-col gap-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                profile.put('/work/employer/profile', { preserveScroll: true });
                            }}
                        >
                            <Field label={t('work.employer.trading_name')} error={profile.errors.trading_name}>
                                <Input
                                    value={profile.data.trading_name}
                                    onChange={(e) => profile.setData('trading_name', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.employer.description')} error={profile.errors.description}>
                                <Textarea
                                    rows={3}
                                    value={profile.data.description}
                                    onChange={(e) => profile.setData('description', e.target.value)}
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('work.employer.sector')}>
                                    <Select
                                        value={profile.data.sector}
                                        onChange={(e) => profile.setData('sector', e.target.value)}
                                    >
                                        {options.sectors.map((s) => (
                                            <option key={s} value={s}>
                                                {t(`work.sector.${s}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('work.employer.size')}>
                                    <Select
                                        value={profile.data.size_band}
                                        onChange={(e) => profile.setData('size_band', e.target.value)}
                                    >
                                        {options.sizes.map((s) => (
                                            <option key={s} value={s}>
                                                {s}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                            <Field label="Website" error={profile.errors.website}>
                                <Input
                                    value={profile.data.website}
                                    onChange={(e) => profile.setData('website', e.target.value)}
                                />
                            </Field>
                            <Field label={t('work.employer.email')} error={profile.errors.contact_email}>
                                <Input
                                    type="email"
                                    value={profile.data.contact_email}
                                    onChange={(e) => profile.setData('contact_email', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" loading={profile.processing}>
                                    {t('work.save')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                    <Card>
                        <CardTitle>{t('work.employer.team')}</CardTitle>
                        <ul className="mt-3 flex flex-col gap-2 text-sm">
                            {team.map((m) => (
                                <li key={`${m.userId}-${m.role}`} className="flex items-center justify-between gap-2">
                                    <span>
                                        <span className="text-fg font-semibold">{m.name}</span>{' '}
                                        <span className="text-fg-muted">{m.phone}</span>{' '}
                                        <Badge>{t(`work.team.role.${m.role}`)}</Badge>
                                    </span>
                                    {m.role === 'recruiter' && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(`/work/employer/team/${m.userId}`, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            {t('hubops.settings.remove')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <form
                            className="border-line mt-4 flex flex-col gap-3 border-t pt-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                member.post('/work/employer/team', {
                                    preserveScroll: true,
                                    onSuccess: () => member.reset(),
                                });
                            }}
                        >
                            <Field label={t('work.team.add')} error={member.errors.phone ?? errors?.phone}>
                                <PhoneInput
                                    value={member.data.phone}
                                    onChange={(_, raw) => member.setData('phone', raw)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" variant="secondary" loading={member.processing}>
                                    {t('work.add')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
