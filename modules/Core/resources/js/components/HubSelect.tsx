import { Select } from '@/components/ui/form';
import { useTranslation } from '@/lib/i18n';

export interface HubOption {
    id: string;
    name: string;
    city: string;
    province: string;
}

/** Hubs grouped by province, with an "I'm not sure yet" option. */
export function HubSelect({
    hubs,
    value,
    onChange,
}: {
    hubs: HubOption[];
    value: string;
    onChange: (id: string) => void;
}) {
    const { t } = useTranslation();
    const provinces = [...new Set(hubs.map((hub) => hub.province))];

    return (
        <Select value={value} onChange={(event) => onChange(event.target.value)}>
            <option value="">{t('profile.home_hub_none')}</option>
            {provinces.map((province) => (
                <optgroup key={province} label={province}>
                    {hubs
                        .filter((hub) => hub.province === province)
                        .map((hub) => (
                            <option key={hub.id} value={hub.id}>
                                {hub.name} ({hub.city})
                            </option>
                        ))}
                </optgroup>
            ))}
        </Select>
    );
}
