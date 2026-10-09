/** Tiny dependency-free SVG bar chart (keeps console pages light). */
export function BarChart({ data, label }: { data: { label: string; value: number }[]; label: string }) {
    const max = Math.max(1, ...data.map((d) => d.value));
    const width = 600;
    const height = 160;
    const gap = 6;
    const bar = (width - gap * (data.length - 1)) / data.length;

    return (
        <figure>
            <svg viewBox={`0 0 ${width} ${height + 20}`} role="img" aria-label={label} className="h-auto w-full">
                {data.map((d, i) => {
                    const h = Math.round((d.value / max) * height);
                    const x = i * (bar + gap);
                    return (
                        <g key={d.label}>
                            <rect x={x} y={height - h} width={bar} height={h} rx={4} fill="var(--color-primary)">
                                <title>{`${d.label}: ${d.value}`}</title>
                            </rect>
                            <text
                                x={x + bar / 2}
                                y={height + 14}
                                textAnchor="middle"
                                fontSize="10"
                                fill="var(--color-fg-muted)"
                            >
                                {d.label}
                            </text>
                        </g>
                    );
                })}
            </svg>
            <figcaption className="sr-only">
                {label}: {data.map((d) => `${d.label} ${d.value}`).join(', ')}
            </figcaption>
        </figure>
    );
}
