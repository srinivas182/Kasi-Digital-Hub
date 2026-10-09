import { router, useForm } from '@inertiajs/react';

import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge, Card, CardTitle } from '@/components/ui/display';
import { Field, Input, Select } from '@/components/ui/form';
import { FileInput } from '@/components/ui/FileInput';
import { useTranslation } from '@/lib/i18n';

import { HubOpsPage } from '../components/HubOpsPage';

interface Props {
    person: { id: string; name: string; preferredName: string | null; placeName: string | null; minor: boolean };
    documents: { id: string; type: string; status: string }[];
    documentTypes: string[];
}

/** Helping a person who is at the hub. The facilitator stays signed in as themselves. */
export default function Assist({ person, documents, documentTypes }: Props) {
    const { t } = useTranslation();
    const profile = useForm({
        preferred_name: person.preferredName ?? '',
        place_name: person.placeName ?? '',
        present: false,
    });
    const doc = useForm<{ type: string; file: File | null; present: boolean }>({
        type: documentTypes[0] ?? 'id_document',
        file: null,
        present: false,
    });

    return (
        <HubOpsPage
            title={t('hubops.assist.title', { name: person.name })}
            crumbs={[{ label: t('hubops.checkin.title'), href: '/hub-ops/check-in' }, { label: person.name }]}
            actions={
                <Button variant="secondary" onClick={() => router.post('/hub-ops/assist/end')}>
                    {t('hubops.assist.end')}
                </Button>
            }
        >
            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardTitle>{t('hubops.assist.profile')}</CardTitle>
                    <form
                        className="mt-4 flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            profile.put('/hub-ops/assist/profile', {
                                preserveScroll: true,
                                onSuccess: () => profile.setData('present', false),
                            });
                        }}
                    >
                        <Field label={t('auth.signup.preferred_name')} error={profile.errors.preferred_name}>
                            <Input
                                value={profile.data.preferred_name}
                                onChange={(e) => profile.setData('preferred_name', e.target.value)}
                            />
                        </Field>
                        <Field label={t('profile.place')} error={profile.errors.place_name}>
                            <Input
                                value={profile.data.place_name}
                                onChange={(e) => profile.setData('place_name', e.target.value)}
                            />
                        </Field>
                        <Checkbox
                            label={t('hubops.assist.present')}
                            checked={profile.data.present}
                            onCheckedChange={(c) => profile.setData('present', c === true)}
                        />
                        {profile.errors.present && <p className="text-danger-text text-sm">{profile.errors.present}</p>}
                        <div>
                            <Button type="submit" loading={profile.processing} disabled={!profile.data.present}>
                                {t('account.save')}
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card>
                    <CardTitle>{t('hubops.assist.document')}</CardTitle>
                    <form
                        className="mt-4 flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            doc.post('/hub-ops/assist/document', {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => doc.reset(),
                            });
                        }}
                    >
                        <Field label={t('documents.type')} error={doc.errors.type}>
                            <Select value={doc.data.type} onChange={(e) => doc.setData('type', e.target.value)}>
                                {documentTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {t(`documents.type.${type}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('documents.file')} error={doc.errors.file}>
                            <FileInput
                                file={doc.data.file}
                                onChange={(file) => doc.setData('file', file)}
                                chooseLabel={t('documents.file')}
                                photoLabel={t('documents.take_photo')}
                            />
                        </Field>
                        <Checkbox
                            label={t('hubops.assist.present')}
                            checked={doc.data.present}
                            onCheckedChange={(c) => doc.setData('present', c === true)}
                        />
                        <div>
                            <Button
                                type="submit"
                                loading={doc.processing}
                                disabled={!doc.data.present || !doc.data.file}
                            >
                                {t('documents.upload')}
                            </Button>
                        </div>
                    </form>
                    <p className="text-fg mt-6 text-sm font-semibold">{t('hubops.assist.documents')}</p>
                    <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {documents.length === 0 && <li className="text-fg-muted">{t('documents.none')}</li>}
                        {documents.map((d) => (
                            <li key={d.id} className="flex justify-between">
                                <span>{t(`documents.type.${d.type}`)}</span>
                                <Badge tone={d.status === 'verified' ? 'success' : 'neutral'}>
                                    {t(`documents.status.${d.status}`)}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </HubOpsPage>
    );
}
