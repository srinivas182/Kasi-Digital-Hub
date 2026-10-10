import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Input, Select } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { STATUS_TONE } from '../../components/status';

interface Props {
    provider: { id: string; name: string; status: string };
    providers: { id: string; name: string }[];
    isAdmin: boolean;
    canAuthor: boolean;
    courses: { id: string; title: string; status: string; changed: boolean; slug: string; reason: string | null }[];
    team: { userId: string; name: string | null; phone: string | null; role: string }[];
    accreditations: { id: string; body: string; number: string; status: string; reason: string | null }[];
    signatory: { signatory_name: string | null; signatory_title: string | null } | null;
    certificates: { id: string; course: string; code: string | null; issuedAt: string; revoked: boolean }[];
}

export default function ProviderDashboard({
    provider,
    providers,
    isAdmin,
    canAuthor,
    courses,
    team,
    accreditations,
    signatory,
    certificates,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const create = useForm({
        title: '',
        topic: 'digital_skills',
        level: 'beginner',
        language: 'English',
        min_age: 18,
        delivery: 'self_paced',
        licence: 'all_rights',
        outcomes: [] as string[],
    });
    const member = useForm({ phone: '', role: 'course_author' });
    const sign = useForm({
        signatory_name: signatory?.signatory_name ?? '',
        signatory_title: signatory?.signatory_title ?? '',
    });
    const claim = useForm<{ body: string; number: string; evidence: File | null }>({
        body: 'QCTO',
        number: '',
        evidence: null,
    });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={provider.name} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{provider.name}</h1>
                    <Badge tone={provider.status === 'verified' ? 'success' : 'warning'}>
                        {t(`work.employer.status.${provider.status}`)}
                    </Badge>
                </div>
                {providers.length > 1 && (
                    <Select
                        aria-label={t('learn.provider.switch')}
                        value={provider.id}
                        onChange={(e) => router.get('/learn/provider', { provider: e.target.value })}
                    >
                        {providers.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </Select>
                )}
            </div>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            <section className="mt-6" aria-labelledby="courses">
                <h2 id="courses" className="text-fg text-lg font-bold">
                    {t('learn.provider.courses')}
                </h2>
                <ul className="mt-3 flex flex-col gap-2">
                    {courses.map((c) => (
                        <li
                            key={c.id}
                            className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                        >
                            <span>
                                <Link
                                    href={`/learn/author/courses/${c.id}`}
                                    className="text-primary font-semibold hover:underline"
                                >
                                    {c.title}
                                </Link>
                                {c.reason && <span className="text-fg-muted block text-xs">{c.reason}</span>}
                            </span>
                            <span className="flex gap-2">
                                <Badge tone={STATUS_TONE[c.status] ?? 'neutral'}>{t(`learn.status.${c.status}`)}</Badge>
                                {c.changed && <Badge tone="warning">{t('learn.status.changed')}</Badge>}
                            </span>
                        </li>
                    ))}
                    {courses.length === 0 && <li className="text-fg-muted">{t('learn.provider.no_courses')}</li>}
                </ul>
                {canAuthor && (
                    <form
                        className="mt-4 flex flex-wrap items-end gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            create.post('/learn/author/courses');
                        }}
                    >
                        <Field
                            label={t('learn.author.new_title')}
                            error={create.errors.title}
                            className="min-w-64 flex-1"
                        >
                            <Input
                                value={create.data.title}
                                onChange={(e) => create.setData('title', e.target.value)}
                            />
                        </Field>
                        <Button
                            type="submit"
                            icon={<Plus className="size-4" aria-hidden />}
                            loading={create.processing}
                            disabled={!create.data.title.trim()}
                        >
                            {t('learn.author.new')}
                        </Button>
                    </form>
                )}
            </section>
            {isAdmin && (
                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardTitle>{t('work.employer.team')}</CardTitle>
                        <ul className="mt-3 flex flex-col gap-2 text-sm">
                            {team.map((m) => (
                                <li key={`${m.userId}-${m.role}`} className="flex items-center justify-between gap-2">
                                    <span>
                                        <span className="text-fg font-semibold">{m.name}</span>{' '}
                                        <span className="text-fg-muted">{m.phone}</span>{' '}
                                        <Badge>{t(`learn.role.${m.role}`)}</Badge>
                                    </span>
                                    {m.userId !== auth.user?.id && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(`/learn/provider/team/${m.userId}/${m.role}`, {
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
                                member.post('/learn/provider/team', {
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
                            <Field label={t('learn.provider.role')}>
                                <Select
                                    value={member.data.role}
                                    onChange={(e) => member.setData('role', e.target.value)}
                                >
                                    {['course_author', 'assessor_moderator', 'provider_admin'].map((r) => (
                                        <option key={r} value={r}>
                                            {t(`learn.role.${r}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <div>
                                <Button type="submit" variant="secondary" loading={member.processing}>
                                    {t('work.add')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                    <Card>
                        <CardTitle>{t('learn.accreditation.title')}</CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">{t('learn.accreditation.hint')}</p>
                        <ul className="mt-3 flex flex-col gap-1 text-sm">
                            {accreditations.map((a) => (
                                <li key={a.id}>
                                    {a.body} {a.number}{' '}
                                    <Badge
                                        tone={
                                            a.status === 'verified'
                                                ? 'success'
                                                : a.status === 'rejected'
                                                  ? 'danger'
                                                  : 'warning'
                                        }
                                    >
                                        {t(`learn.accreditation.${a.status}`)}
                                    </Badge>
                                    {a.reason && <span className="text-fg-muted"> - {a.reason}</span>}
                                </li>
                            ))}
                        </ul>
                        <form
                            className="mt-4 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                claim.post('/learn/provider/accreditations', {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onSuccess: () => claim.reset(),
                                });
                            }}
                        >
                            <Field label={t('learn.accreditation.body')} error={claim.errors.body}>
                                <Input
                                    value={claim.data.body}
                                    onChange={(e) => claim.setData('body', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.accreditation.number')} error={claim.errors.number}>
                                <Input
                                    value={claim.data.number}
                                    onChange={(e) => claim.setData('number', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.accreditation.evidence')} error={claim.errors.evidence}>
                                <FileInput
                                    file={claim.data.evidence}
                                    onChange={(file) => claim.setData('evidence', file)}
                                    chooseLabel={t('documents.file')}
                                    photoLabel={t('documents.take_photo')}
                                />
                            </Field>
                            <div>
                                <Button type="submit" variant="secondary" loading={claim.processing}>
                                    {t('learn.accreditation.claim')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            )}
            {isAdmin && (
                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardTitle>{t('learn.cert.signatory')}</CardTitle>
                        <form
                            className="mt-3 flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                sign.put('/learn/provider/signatory', { preserveScroll: true });
                            }}
                        >
                            <Field label={t('learn.cert.signatory_name')}>
                                <Input
                                    value={sign.data.signatory_name}
                                    onChange={(e) => sign.setData('signatory_name', e.target.value)}
                                />
                            </Field>
                            <Field label={t('learn.cert.signatory_title')}>
                                <Input
                                    value={sign.data.signatory_title}
                                    onChange={(e) => sign.setData('signatory_title', e.target.value)}
                                />
                            </Field>
                            <div>
                                <Button type="submit" variant="secondary">
                                    {t('work.save')}
                                </Button>
                            </div>
                        </form>
                        <p className="mt-4 text-sm">
                            <Link href="/learn/cohorts" className="text-primary font-semibold hover:underline">
                                {t('learn.cohort.title')}
                            </Link>
                        </p>
                    </Card>
                    <Card>
                        <CardTitle>{t('learn.cert.issued')}</CardTitle>
                        <ul className="mt-3 flex flex-col gap-1 text-sm">
                            {certificates.map((c) => (
                                <li key={c.id} className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-fg">
                                        {c.course} <span className="text-fg-muted font-mono">{c.code}</span>
                                    </span>
                                    {c.revoked ? (
                                        <Badge tone="danger">{t('learn.cert.revoked')}</Badge>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => {
                                                const reason = window.prompt(t('learn.cert.revoke_reason'));
                                                if (reason)
                                                    router.post(
                                                        `/learn/certificates/${c.id}/revoke`,
                                                        { reason },
                                                        { preserveScroll: true },
                                                    );
                                            }}
                                        >
                                            {t('learn.cert.revoke')}
                                        </Button>
                                    )}
                                </li>
                            ))}
                            {certificates.length === 0 && <li className="text-fg-muted">{t('learn.review.none')}</li>}
                        </ul>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
