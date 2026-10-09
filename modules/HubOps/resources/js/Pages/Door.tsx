import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { BrandMark } from '@/components/platform/BrandMark';
import { useTranslation } from '@/lib/i18n';
import { Shell } from '@/layouts/Shell';

interface Props {
    hub: { name: string };
    codeUrl: string;
    qr: string;
    url: string;
    secondsLeft: number;
}

/**
 * The door screen: a QR code that changes every 2 minutes. No sign-in, no personal data.
 * It fetches the next code shortly after each change; if the connection drops it says so.
 */
export default function Door({ hub, codeUrl, qr: initialQr, secondsLeft: initialSeconds }: Props) {
    const { t } = useTranslation();
    const [qr, setQr] = useState(initialQr);
    const [offline, setOffline] = useState(false);
    const [nextIn, setNextIn] = useState(initialSeconds);

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout>;
        const refresh = async () => {
            try {
                const response = await fetch(codeUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error(String(response.status));
                const data = (await response.json()) as { qr: string; secondsLeft: number };
                setQr(data.qr);
                setOffline(false);
                setNextIn(data.secondsLeft);
                timer = setTimeout(refresh, (data.secondsLeft + 2) * 1000);
            } catch {
                setOffline(true);
                timer = setTimeout(refresh, 15000);
            }
        };
        timer = setTimeout(refresh, (initialSeconds + 2) * 1000);
        return () => clearTimeout(timer);
    }, [codeUrl, initialSeconds]);

    useEffect(() => {
        const tick = setInterval(() => setNextIn((s) => Math.max(0, s - 1)), 1000);
        return () => clearInterval(tick);
    }, []);

    return (
        <Shell>
            <Head title={hub.name} />
            <main className="bg-kasi-indigo flex min-h-screen flex-col items-center justify-center gap-8 p-6 text-center text-white">
                <BrandMark inverse />
                <div>
                    <h1 className="text-3xl font-bold sm:text-4xl">{t('hubops.door.title')}</h1>
                    <p className="mt-2 text-xl text-white/85">{hub.name}</p>
                </div>
                {offline ? (
                    <p role="alert" className="max-w-md rounded-xl bg-white/10 p-6 text-xl">
                        {t('hubops.door.offline')}
                    </p>
                ) : (
                    <div
                        className="rounded-2xl bg-white p-5 shadow-xl"
                        role="img"
                        aria-label={t('hubops.door.title')}
                        dangerouslySetInnerHTML={{ __html: qr }}
                    />
                )}
                <p className="max-w-lg text-lg text-white/85">{t('hubops.door.steps')}</p>
                <p className="text-base text-white/70">{t('hubops.door.no_phone')}</p>
                <p className="text-sm text-white/50" aria-hidden>
                    {nextIn}s
                </p>
            </main>
        </Shell>
    );
}
