import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle, Download, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, buttonVariants } from '@/components/ui/Button';
import { Badge, Card, CardTitle, ProgressBar } from '@/components/ui/display';
import { AppLayout } from '@/layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';

import { formatBytes } from '../../components/format';
import {
    download,
    downloadedIndex,
    prepare,
    remove,
    STORAGE_CAP_BYTES,
    sync,
    usedBytes,
} from '../../components/offline';

interface Props {
    enrolment: {
        id: string;
        progress: number;
        status: string;
        lastLesson: string | null;
        completedAt: string | null;
        newVersion: boolean;
    };
    course: { title: string; slug: string; dataBytes: number };
    modules: {
        title: string;
        lessons: {
            id: string;
            title: string;
            kind: string;
            minutes: number | null;
            bytes: number;
            done: boolean;
            optional: boolean;
        }[];
    }[];
}

export default function MyCourse({ enrolment, course, modules }: Props) {
    const { t } = useTranslation();
    const { auth, flash } = usePage().props;
    const [downloaded, setDownloaded] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [used, setUsed] = useState(0);
    const first = modules[0]?.lessons[0]?.id;

    useEffect(() => {
        void sync();
        void downloadedIndex().then((index) => setDownloaded(enrolment.id in index));
        void usedBytes().then(setUsed);
    }, [enrolment.id]);

    const startDownload = async () => {
        setMessage(null);
        try {
            setBusy(t('learn.offline.preparing'));
            const data = await prepare(enrolment.id);
            if (!window.confirm(t('learn.offline.confirm', { size: formatBytes(data.bytes) }))) {
                setBusy(null);
                return;
            }
            await download(data, (done, total) => setBusy(t('learn.offline.progress', { done, total })));
            setDownloaded(true);
            setUsed(await usedBytes());
        } catch (e) {
            setMessage(t(e instanceof Error && e.message === 'full' ? 'learn.offline.full' : 'learn.offline.failed'));
        }
        setBusy(null);
    };

    return (
        <AppLayout userName={auth.user?.name}>
            <Head title={course.title} />
            <Link href="/learn/my" className="text-primary text-sm font-semibold hover:underline">
                ← {t('learn.my.title')}
            </Link>
            <h1 className="text-fg mt-2 text-2xl font-bold">{course.title}</h1>
            <div className="mt-2 max-w-xl">
                <ProgressBar
                    value={enrolment.progress}
                    label={t('learn.my.progress', { progress: enrolment.progress })}
                />
            </div>
            {flash.status && (
                <div className="mt-3">
                    <Alert tone="success" title={flash.status} />
                </div>
            )}
            {enrolment.completedAt && (
                <div className="mt-3">
                    <Alert tone="success" title={t('learn.my.completed')} />
                </div>
            )}
            {enrolment.newVersion && (
                <div className="mt-3">
                    <Alert tone="info" title={t('learn.my.new_version')}>
                        <Button
                            size="sm"
                            className="mt-2"
                            onClick={() => router.post(`/learn/my/${enrolment.id}/switch`)}
                        >
                            {t('learn.my.switch')}
                        </Button>
                    </Alert>
                </div>
            )}
            <div className="mt-4 flex flex-wrap gap-2">
                {(enrolment.lastLesson ?? first) && (
                    <Link
                        href={`/learn/my/${enrolment.id}/lessons/${enrolment.lastLesson ?? first}`}
                        className={buttonVariants({})}
                    >
                        {enrolment.lastLesson ? t('learn.my.continue') : t('learn.my.start')}
                    </Link>
                )}
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <Card>
                    <ol className="flex flex-col gap-4">
                        {modules.map((m, i) => (
                            <li key={i}>
                                <h2 className="text-fg font-semibold">{m.title}</h2>
                                <ol className="mt-1 flex flex-col">
                                    {m.lessons.map((l) => (
                                        <li
                                            key={l.id}
                                            className="border-line flex items-center justify-between gap-2 border-b py-2 text-sm"
                                        >
                                            <Link
                                                href={`/learn/my/${enrolment.id}/lessons/${l.id}`}
                                                className="text-primary flex min-h-8 items-center gap-2 font-medium hover:underline"
                                            >
                                                {l.done ? (
                                                    <CheckCircle2
                                                        className="text-success-text size-4"
                                                        aria-label={t('learn.player.finished')}
                                                    />
                                                ) : (
                                                    <Circle className="size-4" aria-hidden />
                                                )}
                                                {l.title}
                                            </Link>
                                            <span className="text-fg-muted flex items-center gap-1">
                                                {l.optional && <Badge>{t('learn.my.optional')}</Badge>}
                                                {t(`learn.kind.${l.kind}`)} · {formatBytes(l.bytes)}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </li>
                        ))}
                    </ol>
                </Card>
                <div className="flex flex-col gap-4">
                    <Card>
                        <CardTitle>{t('learn.offline.title')}</CardTitle>
                        <p className="text-fg-muted mt-1 text-sm">
                            {t('learn.offline.hint', { size: formatBytes(course.dataBytes) })}
                        </p>
                        {busy ? (
                            <p className="text-fg mt-3 text-sm" role="status">
                                {busy}
                            </p>
                        ) : downloaded ? (
                            <div className="mt-3 flex flex-col gap-2">
                                <p className="text-success-text text-sm font-semibold">{t('learn.offline.ready')}</p>
                                <a href="/learn/offline" className="text-primary text-sm font-semibold hover:underline">
                                    {t('learn.offline.open')}
                                </a>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    icon={<Trash2 className="size-4" aria-hidden />}
                                    onClick={async () => {
                                        await remove(enrolment.id);
                                        setDownloaded(false);
                                        setUsed(await usedBytes());
                                    }}
                                >
                                    {t('learn.offline.remove')}
                                </Button>
                            </div>
                        ) : (
                            <Button
                                className="mt-3"
                                icon={<Download className="size-4" aria-hidden />}
                                onClick={startDownload}
                            >
                                {t('learn.offline.download')}
                            </Button>
                        )}
                        {message && <p className="text-danger-text mt-2 text-sm">{message}</p>}
                        <p className="text-fg-muted mt-3 text-xs">
                            {t('learn.offline.used', { used: formatBytes(used), cap: formatBytes(STORAGE_CAP_BYTES) })}
                        </p>
                    </Card>
                    <Button variant="ghost" onClick={() => router.post(`/learn/my/${enrolment.id}/leave`)}>
                        {t('learn.my.leave')}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
