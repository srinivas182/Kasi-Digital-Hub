import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Row {
    id: number;
    rule: string;
    due_on: string;
    status: string;
    overdue: boolean;
    guide: string | null;
}

interface Props {
    business: { id: string; name: string };
    profile: {
        registered_on: string | null;
        turnover_tax: boolean;
        vat_registered: boolean;
        employs: boolean;
        bbbee_on: string | null;
        permit_renewal_on: string | null;
        confirmed: boolean;
    };
    legalForm: string | null;
    open: Row[];
    done: Row[];
}

/** The compliance calendar (S29) - a reminder service, not tax or legal advice. */
export default function CompliancePage({ business, profile, legalForm, open, done }: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({
        registered_on: profile.registered_on ?? '',
        turnover_tax: profile.turnover_tax,
        vat_registered: profile.vat_registered,
        employs: profile.employs,
        bbbee_on: profile.bbbee_on ?? '',
        permit_renewal_on: profile.permit_renewal_on ?? '',
    });
    const company = ['pty', 'coop', 'npc'].includes(legalForm ?? '');
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('biz.compliance.title')} />
            <Link href="/biz" className="text-primary text-sm font-semibold hover:underline">
                ← {business.name}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{t('biz.compliance.title')}</h1>
            <p className="text-fg-muted mt-1 text-sm">{t('biz.compliance.not_advice')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.biz && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.biz} />
                </div>
            )}
            {!profile.confirmed && (
                <div className="mt-3">
                    <Alert tone="info" title={t('biz.compliance.confirm_first')} />
                </div>
            )}
            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="flex flex-col gap-3">
                    <h2 className="text-fg text-lg font-bold">{t('biz.compliance.coming_up')}</h2>
                    {open.map((o) => (
                        <Card key={o.id}>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-fg font-semibold">{t(`biz.compliance.rule.${o.rule}`)}</span>
                                <Badge tone={o.overdue ? 'danger' : 'neutral'}>
                                    {o.overdue
                                        ? t('biz.compliance.overdue', { date: formatDate(o.due_on) })
                                        : t('biz.compliance.due', { date: formatDate(o.due_on) })}
                                </Badge>
                            </div>
                            <p className="text-fg-muted mt-1 text-sm">{t(`biz.compliance.what.${o.rule}`)}</p>
                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                {o.guide && (
                                    <Link
                                        href={`/start/businesses/${business.id}/guide`}
                                        className="text-primary font-semibold hover:underline"
                                    >
                                        {t('biz.compliance.how')}
                                    </Link>
                                )}
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.post(`/biz/compliance/${o.id}/done`, {}, { preserveScroll: true })
                                    }
                                >
                                    {t('biz.compliance.mark_done')}
                                </Button>
                            </div>
                        </Card>
                    ))}
                    {open.length === 0 && <p className="text-fg-muted">{t('biz.compliance.nothing')}</p>}
                    {done.length > 0 && (
                        <>
                            <h2 className="text-fg mt-4 text-lg font-bold">{t('biz.compliance.done_title')}</h2>
                            <ul className="text-fg-muted text-sm">
                                {done.map((o) => (
                                    <li key={o.id}>
                                        ✓ {t(`biz.compliance.rule.${o.rule}`)} · {formatDate(o.due_on)}
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
                <Card className="h-fit">
                    <CardTitle>{t('biz.compliance.about')}</CardTitle>
                    <form
                        className="mt-2 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/biz/compliance/profile', { preserveScroll: true });
                        }}
                    >
                        {company && (
                            <Field label={t('biz.compliance.registered_on')}>
                                <Input
                                    type="date"
                                    value={form.data.registered_on}
                                    onChange={(e) => form.setData('registered_on', e.target.value)}
                                />
                            </Field>
                        )}
                        <Checkbox
                            label={t('biz.compliance.turnover_tax')}
                            checked={form.data.turnover_tax}
                            onCheckedChange={(c) => form.setData('turnover_tax', c === true)}
                        />
                        <Checkbox
                            label={t('biz.compliance.vat')}
                            checked={form.data.vat_registered}
                            onCheckedChange={(c) => form.setData('vat_registered', c === true)}
                        />
                        <Checkbox
                            label={t('biz.compliance.employs')}
                            checked={form.data.employs}
                            onCheckedChange={(c) => form.setData('employs', c === true)}
                        />
                        <Field label={t('biz.compliance.bbbee_on')}>
                            <Input
                                type="date"
                                value={form.data.bbbee_on}
                                onChange={(e) => form.setData('bbbee_on', e.target.value)}
                            />
                        </Field>
                        <Field label={t('biz.compliance.permit_on')}>
                            <Input
                                type="date"
                                value={form.data.permit_renewal_on}
                                onChange={(e) => form.setData('permit_renewal_on', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button type="submit" size="sm" variant="secondary" loading={form.processing}>
                                {t('biz.compliance.save_profile')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
