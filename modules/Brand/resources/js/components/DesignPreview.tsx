import { forwardRef } from 'react';

export interface Kit {
    logo_kind: string;
    logo_url: string | null;
    logo_svg: string;
    primary: string;
    secondary: string;
    on_primary: string;
    lettering: string;
    tagline: string | null;
    contact_lines: string[];
}

const SIZES: Record<string, [number, number]> = {
    a5: [1240, 1748],
    a4: [1240, 1754],
    square: [1080, 1080],
    story: [1080, 1920],
};
const FONTS: Record<string, string> = {
    modern: 'system-ui, sans-serif',
    friendly: '"Trebuchet MS", system-ui, sans-serif',
    classic: 'Georgia, "Times New Roman", serif',
};

/** Split text into lines of at most `max` characters (whole words), capped at `lines` lines. */
export function wrap(text: string, max: number, lines: number): string[] {
    const out: string[] = [];
    for (const paragraph of text.split('\n')) {
        let line = '';
        for (const word of paragraph.split(/\s+/).filter(Boolean)) {
            if ((line + ' ' + word).trim().length > max && line) {
                out.push(line);
                line = word;
            } else {
                line = (line + ' ' + word).trim();
            }
        }
        if (line) out.push(line);
    }
    return out.length > lines ? [...out.slice(0, lines - 1), (out[lines - 1] ?? '').slice(0, max - 1) + '…'] : out;
}

interface Props {
    kit: Kit;
    template: string;
    size: string;
    fields: Record<string, string>;
    businessName: string;
    qr?: string | null;
    photo?: string | null;
}

/** The design drawn as SVG from the brand kit and plain fields (S30). The same SVG becomes the PNG download. */
export const DesignPreview = forwardRef<SVGSVGElement, Props>(function DesignPreview(
    { kit, template, size, fields, businessName, qr, photo },
    ref,
) {
    const [w, h] = SIZES[size] ?? ([1240, 1748] as [number, number]);
    const font = FONTS[kit.lettering] ?? FONTS.modern;
    const pad = Math.round(w * 0.07);
    const headSize = Math.round(w * (size === 'story' ? 0.085 : 0.075));
    const textSize = Math.round(w * 0.04);
    const headLines = wrap(fields.headline ?? '', size === 'square' ? 16 : 18, 3);
    const bodyLines = template === 'price_list' ? [] : wrap(fields.text ?? '', 34, size === 'story' ? 12 : 7);
    const items = template === 'price_list' ? (fields.items ?? '').split('\n').filter(Boolean).slice(0, 20) : [];
    const bandH = Math.round(h * (size === 'story' ? 0.32 : 0.34));
    const logo = Math.round(w * 0.16);
    return (
        <svg
            ref={ref}
            xmlns="http://www.w3.org/2000/svg"
            viewBox={`0 0 ${w} ${h}`}
            width="100%"
            role="img"
            aria-label={`${fields.headline ?? ''} - ${businessName}`}
            style={{ fontFamily: font }}
        >
            <rect width={w} height={h} fill="#FFFFFF" />
            <rect width={w} height={bandH} fill={kit.primary} />
            <rect y={bandH} width={w} height={Math.round(h * 0.012)} fill={kit.secondary} />
            {kit.logo_kind === 'upload' && kit.logo_url ? (
                <image
                    href={kit.logo_url}
                    x={w - pad - logo}
                    y={pad}
                    width={logo}
                    height={logo}
                    preserveAspectRatio="xMidYMid meet"
                />
            ) : (
                <image
                    href={`data:image/svg+xml;utf8,${encodeURIComponent(kit.logo_svg)}`}
                    x={w - pad - logo}
                    y={pad}
                    width={logo}
                    height={logo}
                />
            )}
            {headLines.map((l, i) => (
                <text
                    key={i}
                    x={pad}
                    y={pad + headSize * (i + 1.1)}
                    fontSize={headSize}
                    fontWeight={800}
                    fill={kit.on_primary}
                >
                    {l}
                </text>
            ))}
            <text x={pad} y={bandH - pad * 0.6} fontSize={textSize} fill={kit.on_primary} opacity={0.9}>
                {businessName}
                {kit.tagline ? ` · ${kit.tagline}` : ''}
            </text>
            {photo && (
                <image
                    href={photo}
                    x={pad}
                    y={bandH + pad}
                    width={w - pad * 2}
                    height={Math.round(h * 0.22)}
                    preserveAspectRatio="xMidYMid slice"
                />
            )}
            {bodyLines.map((l, i) => (
                <text
                    key={i}
                    x={pad}
                    y={bandH + pad * 1.6 + (photo ? h * 0.24 : 0) + textSize * 1.35 * i}
                    fontSize={textSize}
                    fill="#1A1A1A"
                >
                    {l}
                </text>
            ))}
            {items.map((line, i) => {
                const [name, price] = line.split('|').map((s) => s.trim());
                const y = bandH + pad * 1.6 + textSize * 1.25 * i;
                return (
                    <g key={i}>
                        <text x={pad} y={y} fontSize={textSize * 0.9} fill="#1A1A1A">
                            {(name ?? '').slice(0, 34)}
                        </text>
                        <text
                            x={w - pad}
                            y={y}
                            fontSize={textSize * 0.9}
                            fill="#1A1A1A"
                            textAnchor="end"
                            fontWeight={700}
                        >
                            {(price ?? '').slice(0, 14)}
                        </text>
                    </g>
                );
            })}
            {(fields.detail || fields.until || fields.link) && (
                <text x={pad} y={h - pad * 2.2} fontSize={textSize * 0.9} fontWeight={700} fill={kit.primary}>
                    {fields.until ? `Until ${fields.until}` : (fields.detail ?? fields.link ?? '').slice(0, 60)}
                </text>
            )}
            <rect y={h - pad * 1.4} width={w} height={pad * 1.4} fill={kit.secondary} />
            <text x={pad} y={h - pad * 0.55} fontSize={textSize * 0.8} fill="#1A1A1A">
                {kit.contact_lines.join('  ·  ').slice(0, 70)}
            </text>
            {qr && (
                <image href={qr} x={w - pad - logo} y={h - pad * 1.4 - logo - pad * 0.4} width={logo} height={logo} />
            )}
        </svg>
    );
});

/** Turn the SVG into a PNG on the phone. Returns false if the browser blocks it (e.g. a photo from another address). */
export async function downloadPng(svg: SVGSVGElement, filename: string): Promise<boolean> {
    const parts = (svg.getAttribute('viewBox') ?? '0 0 1080 1080').split(' ').map(Number);
    const w = parts[2] ?? 1080;
    const h = parts[3] ?? 1080;
    const data = new XMLSerializer().serializeToString(svg);
    const img = new Image();
    img.crossOrigin = 'anonymous';
    const loaded = new Promise<void>((resolve, reject) => {
        img.onload = () => resolve();
        img.onerror = () => reject(new Error('load'));
    });
    img.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(data)}`;
    try {
        await loaded;
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        if (!ctx) return false;
        ctx.drawImage(img, 0, 0, w, h);
        const url = canvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        return true;
    } catch {
        return false;
    }
}
