import { Head, router, useForm, usePage } from '@inertiajs/react';

import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select, Textarea } from '@/components/ui/form';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

interface Article {
    id: number;
    slug: string;
    category: string;
    title: string;
    summary: string;
    body: string;
    status: string;
    needs_review: boolean | number;
    last_checked_on: string | null;
    helpful_yes: number;
    helpful_no: number;
}

export default function HelpAdmin({ articles, categories }: { articles: Article[]; categories: string[] }) {
    const { t } = useTranslation();
    const { auth, flash, errors } = usePage().props;
    const form = useForm({
        id: null as number | null,
        slug: '',
        category: categories[0] ?? 'start',
        title: '',
        summary: '',
        body: '',
    });
    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={t('help_admin.title')} />
            <h1 className="text-fg text-2xl font-bold">{t('help_admin.title')}</h1>
            <p className="text-fg-muted mt-1 text-sm">{t('help_admin.lead')}</p>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {errors?.help && (
                <div className="mt-3">
                    <Alert tone="danger" title={errors.help} />
                </div>
            )}
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_28rem]">
                <Card>
                    <ul className="flex flex-col gap-2 text-sm">
                        {articles.map((a) => (
                            <li
                                key={a.id}
                                className="border-line flex flex-wrap items-center justify-between gap-2 border-b pb-2"
                            >
                                <button
                                    type="button"
                                    className="text-primary text-left font-semibold hover:underline"
                                    onClick={() =>
                                        form.setData({
                                            id: a.id,
                                            slug: a.slug,
                                            category: a.category,
                                            title: a.title,
                                            summary: a.summary,
                                            body: a.body,
                                        })
                                    }
                                >
                                    {a.title}
                                </button>
                                <span className="flex flex-wrap items-center gap-1">
                                    {Boolean(a.needs_review) && (
                                        <Badge tone="warning">{t('help_admin.needs_review')}</Badge>
                                    )}
                                    <Badge>{t(`help_admin.status.${a.status}`)}</Badge>
                                    <span className="text-fg-muted">
                                        👍 {a.helpful_yes} · 👎 {a.helpful_no}
                                    </span>
                                    {a.status === 'review' && (
                                        <>
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/help/${a.id}/review`,
                                                        { publish: true },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('help_admin.publish')}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/help/${a.id}/review`,
                                                        { publish: false },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('help_admin.send_back')}
                                            </Button>
                                        </>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card className="h-fit">
                    <CardTitle>{form.data.id ? t('help_admin.edit') : t('help_admin.new')}</CardTitle>
                    <form
                        className="mt-3 flex flex-col gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/admin/help', { preserveScroll: true });
                        }}
                    >
                        <Field label={t('help_admin.title_field')} error={form.errors.title}>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>
                        <Field label={t('help_admin.slug')} error={form.errors.slug}>
                            <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                        </Field>
                        <Field label={t('help_admin.category')}>
                            <Select
                                value={form.data.category}
                                onChange={(e) => form.setData('category', e.target.value)}
                            >
                                {categories.map((c) => (
                                    <option key={c} value={c}>
                                        {t(`help.category.${c}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('help_admin.summary')} error={form.errors.summary}>
                            <Input
                                value={form.data.summary}
                                onChange={(e) => form.setData('summary', e.target.value)}
                            />
                        </Field>
                        <Field label={t('help_admin.body')} hint={t('help_admin.format_hint')} error={form.errors.body}>
                            <Textarea
                                rows={12}
                                value={form.data.body}
                                onChange={(e) => form.setData('body', e.target.value)}
                            />
                        </Field>
                        <div>
                            <Button type="submit" loading={form.processing}>
                                {t('help_admin.save')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
