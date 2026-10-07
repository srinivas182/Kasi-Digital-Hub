import { useEffect, useRef, useState } from 'react';

const EVENTS = ['pointerdown', 'keydown', 'scroll', 'touchstart'] as const;

/**
 * Tracks inactivity. Used by the kiosk layout to warn and then sign out users on
 * shared hub computers. Returns seconds remaining once the warning period starts.
 */
export function useIdleTimer(options: { timeoutSeconds: number; warningSeconds: number; onTimeout: () => void }) {
    const { timeoutSeconds, warningSeconds, onTimeout } = options;
    const [remaining, setRemaining] = useState<number | null>(null);
    const lastActivity = useRef(0);
    const timeoutRef = useRef(onTimeout);

    useEffect(() => {
        timeoutRef.current = onTimeout;
    }, [onTimeout]);

    useEffect(() => {
        lastActivity.current = Date.now();
        const reset = () => {
            lastActivity.current = Date.now();
            setRemaining(null);
        };
        EVENTS.forEach((event) => window.addEventListener(event, reset, { passive: true }));

        const interval = window.setInterval(() => {
            const idle = (Date.now() - lastActivity.current) / 1000;
            const left = Math.ceil(timeoutSeconds - idle);
            if (left <= 0) {
                setRemaining(null);
                lastActivity.current = Date.now();
                timeoutRef.current();
            } else if (left <= warningSeconds) {
                setRemaining(left);
            }
        }, 1000);

        return () => {
            EVENTS.forEach((event) => window.removeEventListener(event, reset));
            window.clearInterval(interval);
        };
    }, [timeoutSeconds, warningSeconds]);

    return {
        remaining,
        stayActive: () => {
            lastActivity.current = Date.now();
            setRemaining(null);
        },
    };
}
