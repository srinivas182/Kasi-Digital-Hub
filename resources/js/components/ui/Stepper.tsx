import { cn } from '@/lib/cn';

export interface StepperProps {
    steps: string[];
    current: number; // zero-based
}

/** Progress through a multi-step form. Long forms are split into short steps (docs/content-guide.md). */
export function Stepper({ steps, current }: StepperProps) {
    return (
        <div>
            <p className="text-primary mb-2 text-sm font-semibold">
                Step {current + 1} of {steps.length}
                <span className="text-fg-muted font-normal">: {steps[current]}</span>
            </p>
            <ol className="flex gap-1.5" aria-label="Progress">
                {steps.map((step, index) => (
                    <li key={step} className="flex-1">
                        <span
                            className={cn('block h-1.5 rounded-full', index <= current ? 'bg-primary' : 'bg-line')}
                            aria-hidden
                        />
                        <span className="sr-only">
                            {step}
                            {index < current ? ' (done)' : index === current ? ' (current)' : ''}
                        </span>
                    </li>
                ))}
            </ol>
        </div>
    );
}
