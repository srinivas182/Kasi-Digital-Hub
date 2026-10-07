import { useCallback, useEffect, useState } from 'react';

export type ThemeChoice = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'kasi-theme';

function systemPrefersDark(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-color-scheme: dark)').matches
    );
}

export function applyTheme(choice: ThemeChoice): void {
    const dark = choice === 'dark' || (choice === 'system' && systemPrefersDark());
    document.documentElement.classList.toggle('dark', dark);
}

/** Theme choice saved per device. The initial class is set before paint by an inline script in app.blade.php. */
export function useTheme() {
    const [choice, setChoice] = useState<ThemeChoice>(() => {
        if (typeof window === 'undefined') return 'system';
        const saved = window.localStorage.getItem(STORAGE_KEY);
        return saved === 'light' || saved === 'dark' ? saved : 'system';
    });

    useEffect(() => {
        applyTheme(choice);
        if (choice !== 'system' || typeof window.matchMedia !== 'function') return;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const listener = () => applyTheme('system');
        media.addEventListener('change', listener);
        return () => media.removeEventListener('change', listener);
    }, [choice]);

    const update = useCallback((next: ThemeChoice) => {
        if (next === 'system') window.localStorage.removeItem(STORAGE_KEY);
        else window.localStorage.setItem(STORAGE_KEY, next);
        setChoice(next);
    }, []);

    return { choice, setTheme: update };
}
