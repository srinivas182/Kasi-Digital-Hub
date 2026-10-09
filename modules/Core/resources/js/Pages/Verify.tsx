import { Head, router } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field, Input } from '@/components/ui/form';
import { PublicLayout } from '@/layouts/PublicLayout';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

interface Result {
    status: 'valid' | 'revoked' | 'expired' | 'not_found' | 'link_expired';
    type?: string;
    title?: string;
    issuedAt?: string;
    expiresAt?: string | null;
    holder?: string | null;
}

/** Public page: anyone (an employer, a bursary office) can check a KasiHub document. */
export default function Verify({ code, result }: { code: string | null; result: Result | null }) {
    const { t } = useTranslation();
    const [value, setValue] = useState(code ?? '');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/verify', { code: value.trim() });
    };
    const valid = result?.status === 'valid';

    return (
        <PublicLayout>
            <Head title={t('verify.title')} />
            <div className="mx-auto max-w-xl px-4 py-10">
                <h1 className="text-fg text-3xl font-bold">{t('verify.title')}</h1>
                <p className="text-fg-muted mt-2">{t('verify.lead')}</p>
                <form onSubmit={submit} className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <Field label={t('verify.code')} className="flex-1">
                        <Input
                            value={value}
                            onChange={(e) => setValue(e.target.value.toUpperCase())}
                            autoCapitalize="characters"
                            spellCheck={false}
                        />
                    </Field>
                    <Button type="submit">{t('verify.check')}</Button>
                </form>
                {result && (
                    <Card className="mt-6" role="status">
                        <p
                            className={`flex items-center gap-2 text-lg font-bold ${valid ? 'text-success-text' : 'text-danger-text'}`}
                        >
                            {valid ? (
                                <CheckCircle2 className="size-6" aria-hidden />
                            ) : (
                                <XCircle className="size-6" aria-hidden />
                            )}
                            {t(`verify.status.${result.status}`)}
                        </p>
                        {result.title && (
                            <dl className="mt-4 grid grid-cols-[8rem_1fr] gap-y-2 text-sm">
                                <dt className="text-fg-muted">{t('verify.type')}</dt>
                                <dd className="text-fg">{result.title}</dd>
                                <dt className="text-fg-muted">{t('verify.issued')}</dt>
                                <dd className="text-fg">{result.issuedAt ? formatDate(result.issuedAt) : '-'}</dd>
                                {result.expiresAt && (
                                    <>
                                        <dt className="text-fg-muted">{t('verify.expires')}</dt>
                                        <dd className="text-fg">{formatDate(result.expiresAt)}</dd>
                                    </>
                                )}
                                <dt className="text-fg-muted">{t('verify.holder')}</dt>
                                <dd className="text-fg">{result.holder ?? t('verify.holder_hidden')}</dd>
                            </dl>
                        )}
                    </Card>
                )}
            </div>
        </PublicLayout>
    );
}
