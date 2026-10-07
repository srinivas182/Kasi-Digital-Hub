import { cn } from '@/lib/cn';

export type ScoreBand = 'excellent' | 'strong' | 'partial' | 'low';

/** Score bands shared with the matching engine: 9-10 excellent, 7-8 strong, 5-6 partial, below 5 low. */
export function scoreBand(score: number): ScoreBand {
    if (score >= 9) return 'excellent';
    if (score >= 7) return 'strong';
    if (score >= 5) return 'partial';
    return 'low';
}

const BAND_COLOURS: Record<ScoreBand, string> = {
    excellent: 'var(--color-kasi-green)',
    strong: 'var(--color-kasi-action)',
    partial: 'var(--color-kasi-marigold)',
    low: '#9ca0b4',
};

const BAND_LABELS: Record<ScoreBand, string> = {
    excellent: 'Excellent match',
    strong: 'Strong match',
    partial: 'Partial match',
    low: 'Low match',
};

export interface ScoreRingProps {
    score: number;
    size?: number;
    showCaption?: boolean;
    className?: string;
}

/** 0-10 score ring used for job matches (KasiWork) and progress-style scores. */
export function ScoreRing({ score, size = 56, showCaption, className }: ScoreRingProps) {
    const value = Math.max(0, Math.min(10, score));
    const stroke = Math.max(4, size * 0.09);
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const band = scoreBand(value);

    return (
        <span className={cn('inline-flex flex-col items-center', className)}>
            <svg
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                role="img"
                aria-label={`Match score ${value.toFixed(1)} out of 10, ${BAND_LABELS[band].toLowerCase()}`}
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="var(--color-line)"
                    strokeWidth={stroke}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke={BAND_COLOURS[band]}
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={`${(circumference * value) / 10} ${circumference}`}
                    transform={`rotate(-90 ${size / 2} ${size / 2})`}
                />
                <text
                    x="50%"
                    y="50%"
                    dominantBaseline="central"
                    textAnchor="middle"
                    fontWeight={700}
                    fontSize={size * 0.3}
                    fill="var(--color-fg)"
                >
                    {value.toFixed(1)}
                </text>
            </svg>
            {showCaption && <span className="text-fg-muted mt-1 text-xs font-semibold">{BAND_LABELS[band]}</span>}
        </span>
    );
}
