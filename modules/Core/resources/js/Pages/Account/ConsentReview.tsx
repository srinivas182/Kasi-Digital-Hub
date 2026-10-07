import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { AuthLayout } from '@/layouts/AuthLayout';
import { useTranslation } from '@/lib/i18n';

interface LegalDocument {
    key: string;
    version: number;
    title: string;
    summary: string;
}

export default function ConsentReview({ documents }: { documents: LegalDocument[] }) {
    const { t } = useTranslation();
    const form = useForm({ accept_terms: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/consents/review');
    };

    return (
        <AuthLayout title={t('consents.review.title')} description={t('consents.review.description')}>
            <Head title={t('consents.review.title')} />
            <form onSubmit={submit} className="flex flex-col gap-5">
                {documents.map((document) => (
                    <div key={document.key} className="border-line rounded-card border p-4">
                        <p className="text-fg font-semibold">{document.title}</p>
                        <p className="text-fg-muted mt-1 text-sm">{document.summary}</p>
                        <Link
                            href={`/legal/${document.key}`}
                            target="_blank"
                            className="text-primary mt-2 inline-block text-sm font-semibold hover:underline"
                        >
                            {t('auth.signup.read')} {document.title}
                        </Link>
                    </div>
                ))}
                <Checkbox
                    label={t('auth.signup.accept_terms')}
                    checked={form.data.accept_terms}
                    onCheckedChange={(c) => form.setData('accept_terms', c === true)}
                />
                <Button type="submit" size="lg" block disabled={!form.data.accept_terms} loading={form.processing}>
                    {t('consents.review.accept')}
                </Button>
            </form>
        </AuthLayout>
    );
}
