/**
 * KasiLearn offline downloads and progress sync (ADR-022).
 * - Downloads go into the per-person cache `kasi-user-learn` (cleared on sign-out).
 * - Progress made offline is queued in localStorage (`kasi.user.learn.queue`) and sent when online.
 *   The server applies events idempotently ("most progress wins"), so sending twice is harmless.
 */
import type { SnapshotLesson } from './LessonView';

export const CACHE = 'kasi-user-learn';
const INDEX = '/learn/offline-data/index';
const QUEUE = 'kasi.user.learn.queue';
export const STORAGE_CAP_BYTES = 500 * 1024 * 1024;

export interface OfflineCourse {
    enrolment: string;
    title: string;
    modules: {
        title: string;
        lessons: (SnapshotLesson & {
            quiz?: {
                graded: boolean;
                questions: {
                    id: number;
                    kind: string;
                    prompt: string;
                    options: { text: string; correct?: boolean }[];
                    explanation?: string | null;
                }[];
            } | null;
        })[];
    }[];
    media: { url: string; bytes: number }[];
    bytes: number;
    savedAt: string;
}

export interface ProgressEvent {
    enrolment: string;
    lesson: string;
    type: 'completed' | 'position' | 'practice';
    value?: number;
}

const supported = () => typeof window !== 'undefined' && 'caches' in window;

export async function downloadedIndex(): Promise<Record<string, { title: string; bytes: number; savedAt: string }>> {
    if (!supported()) return {};
    const cached = await (await caches.open(CACHE)).match(INDEX);
    return cached ? ((await cached.json()) as Record<string, { title: string; bytes: number; savedAt: string }>) : {};
}

export async function usedBytes(): Promise<number> {
    return Object.values(await downloadedIndex()).reduce((sum, c) => sum + c.bytes, 0);
}

export async function loadCourse(enrolment: string): Promise<OfflineCourse | null> {
    if (!supported()) return null;
    const cached = await (await caches.open(CACHE)).match(`/learn/offline-data/${enrolment}`);
    return cached ? ((await cached.json()) as OfflineCourse) : null;
}

/** Size of a course download before downloading. */
export async function prepare(enrolment: string): Promise<OfflineCourse> {
    const response = await fetch(`/learn/my/${enrolment}/offline`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('prepare');
    return (await response.json()) as OfflineCourse;
}

export async function download(
    course: OfflineCourse,
    onProgress: (done: number, total: number) => void,
): Promise<void> {
    if (!supported()) throw new Error('unsupported');
    if ((await usedBytes()) + course.bytes > STORAGE_CAP_BYTES) throw new Error('full');
    const cache = await caches.open(CACHE);
    const urls = [...new Set(course.media.map((m) => m.url))];
    let done = 0;
    for (const url of urls) {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (response.ok) await cache.put(url, response);
        onProgress(++done, urls.length);
    }
    await cache.put(
        `/learn/offline-data/${course.enrolment}`,
        new Response(JSON.stringify(course), { headers: { 'Content-Type': 'application/json' } }),
    );
    // The offline reader page itself, and its code, so it opens without a connection.
    const reader = await fetch('/learn/offline', { credentials: 'same-origin' });
    if (reader.ok) await cache.put('/learn/offline', reader);
    await import('../Pages/My/Offline');
    const index = await downloadedIndex();
    index[course.enrolment] = { title: course.title, bytes: course.bytes, savedAt: course.savedAt };
    await cache.put(INDEX, new Response(JSON.stringify(index), { headers: { 'Content-Type': 'application/json' } }));
}

export async function remove(enrolment: string): Promise<void> {
    if (!supported()) return;
    const cache = await caches.open(CACHE);
    const course = await loadCourse(enrolment);
    const index = await downloadedIndex();
    delete index[enrolment];
    const stillUsed = new Set<string>();
    for (const other of Object.keys(index)) (await loadCourse(other))?.media.forEach((m) => stillUsed.add(m.url));
    await Promise.all((course?.media ?? []).filter((m) => !stillUsed.has(m.url)).map((m) => cache.delete(m.url)));
    await cache.delete(`/learn/offline-data/${enrolment}`);
    await cache.put(INDEX, new Response(JSON.stringify(index), { headers: { 'Content-Type': 'application/json' } }));
}

export function queue(event: ProgressEvent): void {
    try {
        const items = JSON.parse(window.localStorage.getItem(QUEUE) ?? '[]') as ProgressEvent[];
        items.push(event);
        window.localStorage.setItem(QUEUE, JSON.stringify(items.slice(-2000)));
    } catch {
        // storage full or blocked: progress will be recorded next time online
    }
}

export function pending(): number {
    try {
        return (JSON.parse(window.localStorage.getItem(QUEUE) ?? '[]') as ProgressEvent[]).length;
    } catch {
        return 0;
    }
}

/** Send queued progress; events that reached the server are removed. */
export async function sync(): Promise<number> {
    if (!navigator.onLine) return 0;
    let items: ProgressEvent[];
    try {
        items = JSON.parse(window.localStorage.getItem(QUEUE) ?? '[]') as ProgressEvent[];
    } catch {
        return 0;
    }
    if (items.length === 0) return 0;
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    const sent: ProgressEvent[] = [];
    const byEnrolment = items.reduce<Record<string, ProgressEvent[]>>(
        (acc, e) => ({ ...acc, [e.enrolment]: [...(acc[e.enrolment] ?? []), e] }),
        {},
    );
    for (const [enrolment, events] of Object.entries(byEnrolment)) {
        const response = await fetch(`/learn/my/${enrolment}/progress`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': match?.[1] ? decodeURIComponent(match[1]) : '',
            },
            body: JSON.stringify({
                events: events.map(({ lesson, type, value }) => ({ lesson, type, value: value ?? 0 })),
            }),
        }).catch(() => null);
        if (response?.ok || response?.status === 404) sent.push(...events); // 404: enrolment gone - drop
    }
    const left = items.filter((e) => !sent.includes(e));
    window.localStorage.setItem(QUEUE, JSON.stringify(left));
    return sent.length;
}

/** Record progress: straight to the server when online, otherwise queued. */
export async function record(event: ProgressEvent): Promise<void> {
    queue(event);
    await sync();
}
