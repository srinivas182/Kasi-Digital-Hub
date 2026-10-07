import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import { useId } from 'react';

export interface RadioOption {
    value: string;
    label: string;
    hint?: string;
}

export interface RadioGroupProps extends RadioGroupPrimitive.RadioGroupProps {
    options: RadioOption[];
    legend: string;
}

export function RadioGroup({ options, legend, className, ...props }: RadioGroupProps) {
    const id = useId();
    return (
        <fieldset className={className}>
            <legend className="text-fg mb-2 text-sm font-semibold">{legend}</legend>
            <RadioGroupPrimitive.Root className="flex flex-col gap-2" {...props}>
                {options.map((option) => (
                    <label
                        key={option.value}
                        htmlFor={`${id}-${option.value}`}
                        className="rounded-control border-line bg-surface has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft flex min-h-11 cursor-pointer items-start gap-3 border p-3"
                    >
                        <RadioGroupPrimitive.Item
                            id={`${id}-${option.value}`}
                            value={option.value}
                            className="border-line bg-surface data-[state=checked]:border-primary mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2"
                        >
                            <RadioGroupPrimitive.Indicator className="bg-primary size-2.5 rounded-full" />
                        </RadioGroupPrimitive.Item>
                        <span>
                            <span className="text-fg block text-sm font-semibold">{option.label}</span>
                            {option.hint && <span className="text-fg-muted block text-sm">{option.hint}</span>}
                        </span>
                    </label>
                ))}
            </RadioGroupPrimitive.Root>
        </fieldset>
    );
}
