import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle, ExternalLink, FileDown } from 'lucide-react';
import { useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { FileInput } from '@/components/ui/FileInput';
import { Field, Select } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

import { type BusinessData, BusinessForm } from '../components/BusinessForm';

interface StepItem {
    id: number;
    key: string;
    title: string;
    summary: string;
    why: string | null;
    needs: string[] | null;
    where: string | null;
    link: string | null;
    cost_note: string | null;
    duration: string | null;
    document_type: string | null;
    lastChecked: string | null;
    done: boolean;
    proofStatus: string | null;
}

interface Props {
    person: { name: string; assisted: boolean };
    business: BusinessData & { id: string; readiness: number; legalForm: string | null; formalised: boolean };
    role: string;
    readiness: { score: number; parts: Record<string, { points: number; of: number }>; next: string | null };
    steps: StepItem[];
    members: { id: string; name: string; phone: string; role: string }[];
    documents: { id: string; type: string; status: string }[];
    options: { sectors: string[]; stages: string[]; forms: string[]; turnover: string[] };
    cities: { id: number; name: string }[];
}

function StepCard({
    step,
    businessId,
    documents,
}: {
    step: StepItem;
    businessId: string;
    documents: Props['documents'];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const proof = useForm<{ file: File | null; document_id: string }>({ file: null, document_id: '' });
    const suitable = documents.filter((d) => d.type === step.document_type);

    return (
        <li className="border-line border-b py-3">
            <div className="flex items-start gap-3">
                {step.done ? (
                    <CheckCircle2
                        className="text-success-text mt-0.5 size-5 shrink-0"
                        aria-label={t('start.steps.done_label')}
                    />
                ) : (
                    <Circle className="mt-0.5 size-5 shrink-0" aria-hidden />
                )}
                <div className="min-w-0 flex-1">
                    <button
                        type="button"
                        className="text-fg text-left font-semibold hover:underline"
                        aria-expanded={open}
                        onClick={() => setOpen(!open)}
                    >
                        {step.title}
                    </button>
                    {step.proofStatus && (
                        <Badge tone={step.proofStatus === 'verified' ? 'success' : 'warning'} className="ml-2">
                            {t(`start.proof.${step.proofStatus}`)}
                        </Badge>
                    )}
                    {open && (
                        <div className="mt-2 flex flex-col gap-2 text-sm">
                            <p className="text-fg">{step.summary}</p>
                            {step.why && <p className="text-fg-muted">{step.why}</p>}
                            {step.needs && step.needs.length > 0 && (
                                <div>
                                    <p className="text-fg font-semibold">{t('start.steps.needs')}</p>
                                    <ul className="text-fg list-disc pl-5">
                                        {step.needs.map((n) => (
                                            <li key={n}>{n}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {step.where && (
                                <p className="text-fg">
                                    <strong>{t('start.steps.where')}:</strong> {step.where}{' '}
                                    {step.link && (
                                        <a
                                            href={step.link}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary inline-flex items-center gap-1 font-semibold hover:underline"
                                        >
                                            {t('start.steps.open_site')}{' '}
                                            <ExternalLink className="size-3.5" aria-hidden />
                                        </a>
                                    )}
                                </p>
                            )}
                            <p className="text-fg-muted">
                                {[step.cost_note, step.duration].filter(Boolean).join(' · ')}
                            </p>
                            {step.key === 'bbbee_affidavit' && (
                                <a
                                    href={`/start/businesses/${businessId}/affidavit`}
                                    className={buttonVariants({
                                        size: 'sm',
                                        variant: 'secondary',
                                        className: 'self-start',
                                    })}
                                >
                                    <FileDown className="size-4" aria-hidden /> {t('start.steps.affidavit')}
                                </a>
                            )}
                            {step.key === 'choose_form' ? (
                                <Link
                                    href={`/start/businesses/${businessId}/guide`}
                                    className={buttonVariants({ size: 'sm', className: 'self-start' })}
                                >
                                    {t('start.guide.open')}
                                </Link>
                            ) : step.done ? (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="self-start"
                                    onClick={() =>
                                        router.delete(`/start/businesses/${businessId}/steps/${step.id}`, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    {t('start.steps.undo')}
                                </Button>
                            ) : (
                                <form
                                    className="flex flex-col gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        proof.transform((d) => ({ ...d, document_id: d.document_id || null }));
                                        proof.post(`/start/businesses/${businessId}/steps/${step.id}`, {
                                            forceFormData: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    {step.document_type && (
                                        <>
                                            {suitable.length > 0 && (
                                                <Field label={t('start.steps.existing_proof')}>
                                                    <Select
                                                        value={proof.data.document_id}
                                                        onChange={(e) => proof.setData('document_id', e.target.value)}
                                                    >
                                                        <option value="">-</option>
                                                        {suitable.map((d) => (
                                                            <option key={d.id} value={d.id}>
                                                                {t(`documents.type.${d.type}`)} (
                                                                {t(`documents.status.${d.status}`)})
                                                            </option>
                                                        ))}
                                                    </Select>
                                                </Field>
                                            )}
                                            <Field label={t('start.steps.upload_proof')} error={proof.errors.file}>
                                                <FileInput
                                                    file={proof.data.file}
                                                    onChange={(f) => proof.setData('file', f)}
                                                    chooseLabel={t('documents.file')}
                                                    photoLabel={t('documents.take_photo')}
                                                />
                                            </Field>
                                        </>
                                    )}
                                    <div>
                                        <Button type="submit" size="sm" loading={proof.processing}>
                                            {t('start.steps.mark_done')}
                                        </Button>
                                    </div>
                                </form>
                            )}
                            {step.lastChecked && (
                                <p className="text-fg-muted text-xs">
                                    {t('start.steps.checked', { date: formatDate(step.lastChecked) })}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </li>
    );
}

export default function StartBusiness({
    person,
    business,
    role,
    readiness,
    steps,
    members,
    documents,
    options,
    cities,
}: Props) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const member = useForm({ phone: '' });

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={business.name} />
            {person.assisted && <Alert tone="warning" title={t('work.profile.helping', { name: person.name })} />}
            <Link href="/start" className="text-primary text-sm font-semibold hover:underline">
                ← {t('start.title')}
            </Link>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-fg text-2xl font-bold">{business.name}</h1>
                    <p className="text-fg-muted">
                        {t(`start.stage.${business.stage}`)}
                        {business.legalForm && ` · ${t(`start.form_name.${business.legalForm}`)}`}
                    </p>
                </div>
                {business.formalised && <Badge tone="success">{t('start.formalised')}</Badge>}
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.step && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.step} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('start.steps.title')}</CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">{t('start.steps.disclaimer')}</p>
                        <ol className="mt-2">
                            {steps.map((s) => (
                                <StepCard key={s.id} step={s} businessId={business.id} documents={documents} />
                            ))}
                        </ol>
                    </Card>
                    <Card>
                        <CardTitle>{t('start.profile')}</CardTitle>
                        <div className="mt-4">
                            <BusinessForm
                                business={business}
                                action={`/start/businesses/${business.id}`}
                                method="put"
                                options={options}
                                cities={cities}
                                submitLabel={t('work.save')}
                            />
                        </div>
                    </Card>
                </div>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardTitle>{t('start.readiness_title')}</CardTitle>
                        <div className="mt-3">
                            <ProgressBar
                                value={readiness.score}
                                label={t('start.readiness', { score: readiness.score })}
                            />
                        </div>
                        <ul className="text-fg mt-3 flex flex-col gap-1 text-sm">
                            {Object.entries(readiness.parts).map(([key, p]) => (
                                <li key={key} className="flex justify-between">
                                    <span>{t(`start.part.${key}`)}</span>
                                    <span className="text-fg-muted">
                                        {p.points} / {p.of}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-4 flex flex-col gap-2">
                            <Link href={`/start/businesses/${business.id}/plan`} className={buttonVariants({})}>
                                {t('start.plan.open')}
                            </Link>
                            <a
                                href={`/start/businesses/${business.id}/summary`}
                                className={buttonVariants({ variant: 'secondary' })}
                            >
                                <FileDown className="size-4" aria-hidden /> {t('start.summary')}
                            </a>
                        </div>
                    </Card>
                    <Card>
                        <CardTitle>{t('start.members.title')}</CardTitle>
                        <ul className="mt-2 flex flex-col gap-1 text-sm">
                            {members.map((m) => (
                                <li key={m.id} className="flex items-center justify-between gap-2">
                                    <span>
                                        <span className="text-fg font-medium">{m.name}</span>{' '}
                                        <Badge>{t(`start.members.role.${m.role}`)}</Badge>
                                    </span>
                                    {role === 'owner' && m.role === 'coowner' && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(`/start/businesses/${business.id}/members/${m.id}`, {
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
                        {role === 'owner' && (
                            <form
                                className="mt-3 flex flex-col gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    member.post(`/start/businesses/${business.id}/members`, {
                                        preserveScroll: true,
                                        onSuccess: () => member.reset(),
                                    });
                                }}
                            >
                                <Field label={t('start.members.add')} error={member.errors.phone ?? errors?.phone}>
                                    <PhoneInput
                                        value={member.data.phone}
                                        onChange={(_, raw) => member.setData('phone', raw)}
                                    />
                                </Field>
                                <div>
                                    <Button type="submit" size="sm" variant="secondary">
                                        {t('work.add')}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
