import { Badge } from '@/components/ui/display';
import { formatDateTime } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

/** "Sat 14 Nov 2026, 10:00 - 13:00" in South African time. */
export function eventWhen(startsAt: string, endsAt: string) {
    const end = new Intl.DateTimeFormat('en-ZA', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: 'Africa/Johannesburg',
    }).format(new Date(endsAt));
    return `${formatDateTime(startsAt)} - ${end}`;
}

export function EventTypeBadge({ type }: { type: string }) {
    const { t } = useTranslation();
    const tone = type === 'job_day' ? 'primary' : type === 'class' ? 'success' : 'neutral';
    return <Badge tone={tone}>{t(`hubops.events.type.${type}`)}</Badge>;
}
