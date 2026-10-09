import { useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/display';
import { Field } from '@/components/ui/form';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { useTranslation } from '@/lib/i18n';

import { type HubChoice, HubOpsPage } from '../../components/HubOpsPage';

/** Step 1 of assisted registration: a code goes to the person's own phone. */
export default function RegisterPhone({ hubs }: { hubs: HubChoice }) {
    const { t } = useTranslation();
    const form = useForm({ phone: '' });

    return (
        <HubOpsPage
            title={t('hubops.register.title')}
            hubs={hubs}
            crumbs={[
                { label: t('hubops.checkin.title'), href: '/hub-ops/check-in' },
                { label: t('hubops.register.title') },
            ]}
        >
            <Card className="max-w-xl">
                <p className="text-fg-muted">{t('hubops.register.phone_intro')}</p>
                <form
                    className="mt-4 flex flex-col gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/hub-ops/register/code');
                    }}
                >
                    <Field label={t('auth.phone.label')} error={form.errors.phone} required>
                        <PhoneInput value={form.data.phone} onChange={(_, raw) => form.setData('phone', raw)} />
                    </Field>
                    <div>
                        <Button type="submit" loading={form.processing}>
                            {t('hubops.register.send')}
                        </Button>
                    </div>
                </form>
            </Card>
        </HubOpsPage>
    );
}
