/**
 * South African formatting helpers. Must stay in step with App\Support\Format\SaFormat (PHP);
 * both are tested against tests/fixtures/format-cases.json.
 */

const NBSP = '\u00A0';
const TIME_ZONE = 'Africa/Johannesburg';

/** R1 234.56 - rand sign, non-breaking space thousands separator, dot decimal. */
export function formatMoney(cents: number, options: { wholeRands?: boolean } = {}): string {
    const negative = cents < 0;
    const abs = Math.abs(cents);
    const rands = Math.floor(abs / 100);
    const rest = abs % 100;
    const grouped = String(rands).replace(/\B(?=(\d{3})+(?!\d))/g, NBSP);
    const value = options.wholeRands ? grouped : `${grouped}.${String(rest).padStart(2, '0')}`;
    return `${negative ? '-' : ''}R${value}`;
}

/** Normalise a South African number typed in any common way to E.164 (+27XXXXXXXXX), or null if invalid. */
export function normalisePhone(input: string): string | null {
    let digits = input.replace(/[^\d+]/g, '');
    if (digits.startsWith('+27')) digits = digits.slice(3);
    else if (digits.startsWith('0027')) digits = digits.slice(4);
    else if (digits.startsWith('27') && digits.length === 11) digits = digits.slice(2);
    else if (digits.startsWith('0')) digits = digits.slice(1);
    if (!/^[1-8]\d{8}$/.test(digits)) return null;
    return `+27${digits}`;
}

/** +27724183390 -> 072 418 3390 */
export function formatPhone(e164: string): string {
    const normalised = normalisePhone(e164);
    if (!normalised) return e164;
    const local = `0${normalised.slice(3)}`;
    return `${local.slice(0, 3)} ${local.slice(3, 6)} ${local.slice(6)}`;
}

/** 7 Oct 2026 (SAST) */
export function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: TIME_ZONE,
    }).format(new Date(iso));
}

/** 7 Oct 2026, 14:30 (SAST, 24-hour) */
export function formatDateTime(iso: string): string {
    const date = new Date(iso);
    const time = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: TIME_ZONE,
    }).format(date);
    return `${formatDate(iso)}, ${time}`;
}
