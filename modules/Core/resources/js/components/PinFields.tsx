import { OtpInput } from '@/components/ui/OtpInput';
import { useTranslation } from '@/lib/i18n';

interface PinFieldsProps {
    pin: string;
    confirmation: string;
    onPin: (value: string) => void;
    onConfirmation: (value: string) => void;
    error?: string;
    labelKey?: string;
}

/** Choose-a-PIN pair (PIN + repeat) with the PIN rules explained up front. */
export function PinFields({
    pin,
    confirmation,
    onPin,
    onConfirmation,
    error,
    labelKey = 'auth.pin.choose',
}: PinFieldsProps) {
    const { t } = useTranslation();
    const mismatch = confirmation.length === 5 && confirmation !== pin;

    return (
        <div className="flex flex-col gap-5">
            <div>
                <p className="text-fg text-sm font-semibold">{t(labelKey)}</p>
                <p className="text-fg-muted mb-2 text-sm">{t('auth.pin.choose_hint')}</p>
                <OtpInput length={5} secret value={pin} onChange={onPin} label={t(labelKey)} invalid={Boolean(error)} />
            </div>
            <div>
                <p className="text-fg mb-2 text-sm font-semibold">{t('auth.pin.confirm')}</p>
                <OtpInput
                    length={5}
                    secret
                    value={confirmation}
                    onChange={onConfirmation}
                    label={t('auth.pin.confirm')}
                    invalid={mismatch}
                />
            </div>
            {(error || mismatch) && (
                <p role="alert" className="text-danger-text text-sm font-medium">
                    {error ?? t('auth.pin.mismatch')}
                </p>
            )}
        </div>
    );
}
