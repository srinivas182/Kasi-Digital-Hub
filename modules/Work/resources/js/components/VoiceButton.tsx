import { Mic, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

import { postJson } from './postJson';

const LANGUAGE_NAMES: Record<string, string> = {
    en: 'English',
    zu: 'isiZulu',
    xh: 'isiXhosa',
    ts: 'Xitsonga',
    nso: 'Sepedi',
    af: 'Afrikaans',
    st: 'Sesotho',
    tn: 'Setswana',
    ve: 'Tshivenda',
};
const MAX_SECONDS = 120;

/**
 * Record a voice note and turn it into text the person can edit. Only switched-on languages are
 * offered; the audio is sent once and not kept.
 */
export function VoiceButton({ languages, onText }: { languages: string[]; onText: (text: string) => void }) {
    const { t } = useTranslation();
    const [language, setLanguage] = useState(languages[0] ?? 'en');
    const [recording, setRecording] = useState(false);
    const [busy, setBusy] = useState(false);
    const [seconds, setSeconds] = useState(0);
    const [message, setMessage] = useState<string | null>(null);
    const recorder = useRef<MediaRecorder | null>(null);
    const chunks = useRef<Blob[]>([]);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(
        () => () => {
            if (timer.current) clearInterval(timer.current);
        },
        [],
    );

    if (languages.length === 0) {
        return <p className="text-fg-muted text-sm">{t('work.voice.not_available')}</p>;
    }

    const send = async (blob: Blob, length: number) => {
        setBusy(true);
        const form = new FormData();
        form.append('audio', blob, 'voice-note');
        form.append('language', language);
        form.append('seconds', String(Math.max(1, Math.min(MAX_SECONDS, length))));
        try {
            const result = await postJson<{ ok: boolean; text?: string; message?: string }>('/work/voice', form);
            if (result.ok && result.text) onText(result.text);
            else setMessage(result.message ?? t('work.voice.fallback.unavailable'));
        } catch {
            setMessage(t('work.voice.fallback.unavailable'));
        } finally {
            setBusy(false);
        }
    };

    const start = async () => {
        setMessage(null);
        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            setMessage(t('work.voice.no_mic'));
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const rec = new MediaRecorder(stream);
            chunks.current = [];
            rec.ondataavailable = (e) => chunks.current.push(e.data);
            let length = 0;
            rec.onstop = () => {
                stream.getTracks().forEach((track) => track.stop());
                if (timer.current) clearInterval(timer.current);
                void send(new Blob(chunks.current, { type: rec.mimeType || 'audio/webm' }), length);
            };
            recorder.current = rec;
            rec.start();
            setRecording(true);
            setSeconds(0);
            timer.current = setInterval(() => {
                length += 1;
                setSeconds(length);
                if (length >= MAX_SECONDS) stop();
            }, 1000);
        } catch {
            setMessage(t('work.voice.no_mic'));
        }
    };

    const stop = () => {
        recorder.current?.stop();
        setRecording(false);
    };

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-end gap-2">
                {languages.length > 1 && (
                    <Select
                        aria-label={t('work.voice.language')}
                        value={language}
                        onChange={(e) => setLanguage(e.target.value)}
                        className="max-w-40"
                    >
                        {languages.map((l) => (
                            <option key={l} value={l}>
                                {LANGUAGE_NAMES[l] ?? l}
                            </option>
                        ))}
                    </Select>
                )}
                {recording ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="danger"
                        icon={<Square className="size-4" aria-hidden />}
                        onClick={stop}
                    >
                        {t('work.voice.stop')} ({seconds}s)
                    </Button>
                ) : (
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        icon={<Mic className="size-4" aria-hidden />}
                        loading={busy}
                        onClick={start}
                    >
                        {busy ? t('work.voice.working') : t('work.voice.record')}
                    </Button>
                )}
            </div>
            <p className="text-fg-muted text-xs">{t('work.voice.notice')}</p>
            {message && (
                <p role="status" className="text-fg text-sm">
                    {message}
                </p>
            )}
        </div>
    );
}
