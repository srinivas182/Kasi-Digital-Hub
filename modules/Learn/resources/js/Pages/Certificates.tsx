import { Head, router, usePage } from '@inertiajs/react';
import { Award, Download, FileText, Link2, MessageCircle } from 'lucide-react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, EmptyState } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Cert {
    id: string;
    kind: string;
    course: string;
    provider: string;
    issuedAt: string;
    code: string | null;
    showOnCv: boolean;
    revoked: boolean;
    reason: string | null;
}

export default function Certificates({ certificates }: { certificates: Cert[] }) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const shareUrl = (usePage().props as { flash: { share_url?: string } }).flash.share_url;

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('learn.cert.title')} />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-fg text-2xl font-bold">{t('learn.cert.title')}</h1>
                {certificates.some((c) => !c.revoked) && (
                    <a href="/learn/certificates/record" className={buttonVariants({ variant: 'secondary' })}>
                        <FileText className="size-4" aria-hidden /> {t('learn.cert.record')}
                    </a>
                )}
            </div>
            {flash.status && (
                <div className="mt-4">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {shareUrl && (
                <div className="mt-4">
                    <Alert tone="success" title={t('work.cv.share_ready')}>
                        <code className="break-all">{shareUrl}</code>
                        <a
                            href={`https://wa.me/?text=${encodeURIComponent(t('learn.cert.whatsapp_text', { url: shareUrl }))}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={buttonVariants({ size: 'sm', className: 'mt-2' })}
                        >
                            <MessageCircle className="size-4" aria-hidden /> {t('work.cv.whatsapp')}
                        </a>
                    </Alert>
                </div>
            )}
            {certificates.length === 0 ? (
                <div className="mt-6">
                    <EmptyState icon={<Award className="size-8" aria-hidden />} title={t('learn.cert.none')} />
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2">
                    {certificates.map((c) => (
                        <li key={c.id}>
                            <Card className="h-full">
                                <div className="flex flex-wrap gap-2">
                                    <Badge tone={c.revoked ? 'danger' : 'success'}>
                                        {c.revoked ? t('learn.cert.revoked') : t(`learn.cert.kind.${c.kind}`)}
                                    </Badge>
                                </div>
                                <p className="text-fg mt-2 text-lg font-semibold">{c.course}</p>
                                <p className="text-fg-muted text-sm">
                                    {c.provider} · {formatDate(c.issuedAt)}
                                </p>
                                {c.code && (
                                    <p className="text-fg-muted text-xs">
                                        {t('work.cv.code')}: <span className="font-mono">{c.code}</span>
                                    </p>
                                )}
                                {c.revoked ? (
                                    <p className="text-danger-text mt-2 text-sm">{c.reason}</p>
                                ) : (
                                    <>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <a
                                                href={`/learn/certificates/${c.id}/download`}
                                                className={buttonVariants({ size: 'sm', variant: 'secondary' })}
                                            >
                                                <Download className="size-4" aria-hidden /> {t('work.cv.download')}
                                            </a>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                icon={<Link2 className="size-4" aria-hidden />}
                                                onClick={() =>
                                                    router.post(
                                                        `/learn/certificates/${c.id}/share`,
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('work.cv.share')}
                                            </Button>
                                        </div>
                                        <div className="mt-3">
                                            <Checkbox
                                                label={t('learn.cert.on_cv')}
                                                checked={c.showOnCv}
                                                onCheckedChange={(v) =>
                                                    router.post(
                                                        `/learn/certificates/${c.id}/cv`,
                                                        { show: v === true },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            />
                                        </div>
                                    </>
                                )}
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
