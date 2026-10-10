/**
 * Per-person offline data (ADR-022): caches named `kasi-user-*` and localStorage keys starting
 * `kasi.user.` hold things a person saved for offline use. They are removed when the person signs
 * out, because hub computers and family phones are shared.
 */
export async function clearPersonalOfflineData(): Promise<void> {
    try {
        Object.keys(window.localStorage)
            .filter((key) => key.startsWith('kasi.user.'))
            .forEach((key) => window.localStorage.removeItem(key));
    } catch {
        // storage blocked - nothing to clear
    }
    if ('caches' in window) {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => key.startsWith('kasi-user-')).map((key) => caches.delete(key)));
    }
}
