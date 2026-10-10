import { clsx, type ClassValue } from 'clsx';

/**
 * Merge class names, letting later Tailwind classes override earlier ones in the same group
 * ("p-5" then "p-3" keeps "p-3"; "text-sm" and "text-fg" are different groups, so both stay).
 *
 * A small, purpose-built replacement for tailwind-merge (~8 KB gzip on every page). It covers the
 * utility groups this design system overrides; anything it doesn't recognise is kept as written.
 * Rules are covered by tests in resources/js/lib/cn.test.ts.
 */
const TEXT_SIZES = new Set(['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl']);
const ALIGN = new Set(['left', 'center', 'right', 'justify', 'start', 'end']);
const DISPLAY = new Set([
    'block',
    'inline-block',
    'inline',
    'flex',
    'inline-flex',
    'grid',
    'inline-grid',
    'hidden',
    'contents',
    'table',
]);
const POSITION = new Set(['static', 'fixed', 'absolute', 'relative', 'sticky']);
const FONT_WEIGHTS = new Set([
    'thin',
    'extralight',
    'light',
    'normal',
    'medium',
    'semibold',
    'bold',
    'extrabold',
    'black',
]);
const BORDER_WIDTH = /^border(-[trblxy])?(-\d+)?$/;
const ROUNDED = /^rounded(-[a-z0-9]+)*$/;

function group(utility: string): string | null {
    const negative = utility.startsWith('-') ? utility.slice(1) : utility;
    if (DISPLAY.has(negative)) return 'display';
    if (POSITION.has(negative)) return 'position';

    const dash = negative.indexOf('-');
    const head = dash === -1 ? negative : negative.slice(0, dash);
    const rest = dash === -1 ? '' : negative.slice(dash + 1);

    switch (head) {
        case 'p':
        case 'px':
        case 'py':
        case 'pt':
        case 'pr':
        case 'pb':
        case 'pl':
        case 'm':
        case 'mx':
        case 'my':
        case 'mt':
        case 'mr':
        case 'mb':
        case 'ml':
        case 'w':
        case 'h':
        case 'size':
        case 'z':
        case 'opacity':
        case 'shadow':
        case 'leading':
        case 'tracking':
        case 'order':
        case 'basis':
            return head;
        case 'min':
        case 'max':
            return rest.startsWith('w') || rest.startsWith('h') ? `${head}-${rest[0]}` : null;
        case 'gap':
            return rest.startsWith('x-') ? 'gap-x' : rest.startsWith('y-') ? 'gap-y' : 'gap';
        case 'text':
            if (TEXT_SIZES.has(rest) || (rest.startsWith('[') && /\d(px|rem|em)/.test(rest))) return 'text-size';
            if (ALIGN.has(rest)) return 'text-align';
            return 'text-color';
        case 'font':
            return FONT_WEIGHTS.has(rest) ? 'font-weight' : 'font-family';
        case 'bg':
            return 'bg';
        case 'items':
        case 'justify':
        case 'self':
        case 'content':
            return head;
        case 'grid':
            return rest.startsWith('cols') ? 'grid-cols' : rest.startsWith('rows') ? 'grid-rows' : null;
        case 'col':
            return 'col';
        case 'flex':
            return ['row', 'col', 'row-reverse', 'col-reverse'].includes(rest)
                ? 'flex-direction'
                : ['wrap', 'nowrap', 'wrap-reverse'].includes(rest)
                  ? 'flex-wrap'
                  : 'flex';
        case 'overflow':
            return rest.startsWith('x') ? 'overflow-x' : rest.startsWith('y') ? 'overflow-y' : 'overflow';
        case 'cursor':
            return 'cursor';
        default:
            break;
    }
    if (BORDER_WIDTH.test(negative)) return `border-width${negative.match(/^border(-[trblxy])?/)?.[1] ?? ''}`;
    if (head === 'border') return 'border-color';
    if (ROUNDED.test(negative)) return 'rounded';
    return null;
}

export function cn(...inputs: ClassValue[]): string {
    const classes = clsx(inputs).split(/\s+/).filter(Boolean);
    const seen = new Map<string, number>();
    const keep: (string | null)[] = [];

    classes.forEach((cls) => {
        const colon = cls.lastIndexOf(':');
        const variants = colon === -1 ? '' : cls.slice(0, colon + 1);
        const utility = (colon === -1 ? cls : cls.slice(colon + 1)).replace(/^!/, '');
        const g = group(utility);
        if (g !== null) {
            const key = variants + g;
            const previous = seen.get(key);
            if (previous !== undefined) keep[previous] = null; // a later class in the same group wins
            seen.set(key, keep.length);
        }
        keep.push(cls);
    });

    return keep.filter((c): c is string => c !== null).join(' ');
}
