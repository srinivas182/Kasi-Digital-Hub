import { Alert } from '@/components/ui/Alert';
import { useTranslation } from '@/lib/i18n';

/** Demo environments only: shows the code that would have been sent by SMS or shown in an authenticator. */
export function DemoCode({ code, label = 'auth.code.demo' }: { code?: string | null; label?: string }) {
    const { t } = useTranslation();
    if (!code) return null;
    return (
        <div className="mb-4" data-testid="demo-code">
            <Alert tone="warning" title={t(label, { code })} />
        </div>
    );
}
