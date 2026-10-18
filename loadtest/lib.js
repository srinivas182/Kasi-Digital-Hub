// Shared helpers for the k6 load tests (S22). Run against staging, never production.
import http from 'k6/http';
import { check } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8123';
const DEMO_PIN = __ENV.DEMO_PIN || '24680';
let inertiaVersion = null;
let signedIn = false;

/** Each virtual user looks like its own visitor (needs TRUSTED_PROXIES on the test environment only). */
function ip() {
    return `10.${(__VU >> 16) & 255}.${(__VU >> 8) & 255}.${__VU & 255}`;
}

function base() {
    return { 'X-Forwarded-For': ip() };
}

/** The asset version, read once from the first full page (Inertia answers page requests without it with 409). */
function version() {
    if (inertiaVersion === null) {
        const res = http.get(`${BASE}/login`, { headers: base() });
        // The page object's own version follows its URL (the app also has a 'version' setting, which must not be used).
        const match = /"url":"[^"]*","version":"([^"]*)"/.exec(res.body || '');
        inertiaVersion = match ? match[1] : '';
    }
    return inertiaVersion;
}

/** Inertia page request: the header makes Laravel return the page as JSON, like the app does after first load. */
export function visit(path, inertia = true) {
    const headers = inertia
        ? {
              ...base(),
              'X-Inertia': 'true',
              'X-Inertia-Version': version(),
              'X-Requested-With': 'XMLHttpRequest',
              Accept: 'text/html, application/xhtml+xml',
          }
        : base();
    const res = http.get(`${BASE}${path}`, {
        headers,
        tags: { page: path.split('?')[0].replace(/\/[0-9A-Za-z]{26}/g, '/:id') },
    });
    check(res, { [`${path} ok`]: (r) => r.status === 200 });
    return res;
}

function xsrf() {
    const jar = http.cookieJar();
    const cookies = jar.cookiesForURL(BASE);
    return cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';
}

/** Sign in once per virtual user, with phone + PIN (demo accounts in demo mode); later calls reuse the session. */
export function signIn(phone) {
    if (signedIn) {
        return;
    }
    signedIn = true;
    http.get(`${BASE}/login`, { headers: base() });
    const headers = () => ({ ...base(), 'X-XSRF-TOKEN': xsrf(), 'X-Inertia': 'true', 'X-Inertia-Version': version(), 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' });
    // A new device gets a one-time code by SMS; in demo mode (staging only) the code page shows it.
    http.post(`${BASE}/login`, JSON.stringify({ phone }), { headers: headers(), redirects: 0 });
    const code = http.get(`${BASE}/login/code`, { headers: headers() });
    const demoCode = /"demoCode":"(\d+)"/.exec(code.body || '');
    if (demoCode) {
        http.post(`${BASE}/login/code`, JSON.stringify({ code: demoCode[1] }), { headers: headers() });
    }
    const res = http.post(`${BASE}/login/pin`, JSON.stringify({ pin: DEMO_PIN }), { headers: headers() });
    check(res, { 'signed in': (r) => r.status < 400 });
}

/** One of the 500 demo citizens (+2784000xxxx), spread across virtual users. */
export function demoCitizen() {
    const n = ((__VU - 1) % 500) + 1;
    return `+2784000${String(n).padStart(4, '0')}`;
}

/** Realistic think time: a person reads for a few seconds between pages. */
export function think(min = 3, max = 12) {
    return min + Math.random() * (max - min);
}
