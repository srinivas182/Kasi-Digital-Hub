import { Camera, Paperclip } from 'lucide-react';
import { useId, useRef } from 'react';

import { cn } from '@/lib/cn';

import { useField } from './form';

export interface FileInputProps {
    file: File | null;
    onChange: (file: File | null) => void;
    accept?: string;
    chooseLabel: string;
    /** When set, phones also get a button that opens the camera directly. */
    photoLabel?: string;
}

function size(bytes: number): string {
    return bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** File picker with an optional "Take a photo" button that opens the phone camera. */
export function FileInput({
    file,
    onChange,
    accept = '.pdf,.jpg,.jpeg,.png',
    chooseLabel,
    photoLabel,
}: FileInputProps) {
    const field = useField();
    const generated = useId();
    const fileRef = useRef<HTMLInputElement>(null);
    const cameraRef = useRef<HTMLInputElement>(null);
    const id = field.id ?? generated;
    const button =
        'rounded-control border-line bg-surface text-fg hover:bg-surface-muted inline-flex min-h-11 items-center gap-2 border px-4 text-sm font-semibold';

    return (
        <div className={cn('flex flex-col gap-2', field.invalid && 'text-danger-text')}>
            <div className="flex flex-wrap gap-2">
                <button type="button" className={button} onClick={() => fileRef.current?.click()}>
                    <Paperclip className="size-4" aria-hidden /> {chooseLabel}
                </button>
                {photoLabel && (
                    <button type="button" className={button} onClick={() => cameraRef.current?.click()}>
                        <Camera className="size-4" aria-hidden /> {photoLabel}
                    </button>
                )}
            </div>
            <input
                ref={fileRef}
                id={id}
                type="file"
                accept={accept}
                className="sr-only"
                aria-describedby={[field.hintId, field.errorId].filter(Boolean).join(' ') || undefined}
                aria-invalid={field.invalid || undefined}
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
            {photoLabel && (
                <input
                    ref={cameraRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden
                    onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                />
            )}
            {file && (
                <p className="text-fg text-sm" aria-live="polite">
                    {file.name} <span className="text-fg-muted">({size(file.size)})</span>
                </p>
            )}
        </div>
    );
}
